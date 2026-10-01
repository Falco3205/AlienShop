#!/usr/bin/env python3
"""Server SMTP + IMAP minimale per i test della PEC (solo uso locale)."""
import asyncio, os, sys, json, itertools

root = sys.argv[1]
smtp_port = int(sys.argv[2])
imap_port = int(sys.argv[3])
os.makedirs(os.path.join(root, 'outbox'), exist_ok=True)
os.makedirs(os.path.join(root, 'mailbox'), exist_ok=True)
seen = set()
counter = itertools.count(1)


async def smtp(reader, writer):
    writer.write(b'220 fake-pec ESMTP\r\n')
    await writer.drain()
    data_mode = False
    buf = []
    while True:
        line = await reader.readline()
        if not line:
            break
        if data_mode:
            if line in (b'.\r\n', b'.\n'):
                data_mode = False
                path = os.path.join(root, 'outbox', '%d.eml' % next(counter))
                with open(path, 'wb') as f:
                    f.write(b''.join(buf))
                writer.write(b'250 OK queued\r\n')
            else:
                buf.append(line[1:] if line.startswith(b'..') else line)
            await writer.drain()
            continue
        cmd = line.decode(errors='ignore').strip()
        up = cmd.upper()
        if up.startswith('EHLO') or up.startswith('HELO'):
            writer.write(b'250-fake-pec\r\n250 AUTH LOGIN PLAIN\r\n')
        elif up.startswith('AUTH LOGIN'):
            writer.write(b'334 VXNlcm5hbWU6\r\n'); await writer.drain(); await reader.readline()
            writer.write(b'334 UGFzc3dvcmQ6\r\n'); await writer.drain(); await reader.readline()
            writer.write(b'235 ok\r\n')
        elif up.startswith('MAIL FROM') or up.startswith('RCPT TO'):
            with open(os.path.join(root, 'envelope.log'), 'a') as f:
                f.write(cmd + '\n')
            writer.write(b'250 OK\r\n')
        elif up == 'DATA':
            data_mode = True
            buf = []
            writer.write(b'354 go\r\n')
        elif up == 'QUIT':
            writer.write(b'221 bye\r\n'); await writer.drain(); break
        else:
            writer.write(b'250 OK\r\n')
        await writer.drain()
    writer.close()


def messages():
    out = {}
    for name in sorted(os.listdir(os.path.join(root, 'mailbox'))):
        if name.endswith('.eml'):
            out[int(name[:-4])] = os.path.join(root, 'mailbox', name)
    return out


async def imap(reader, writer):
    writer.write(b'* OK fake-imap ready\r\n')
    await writer.drain()
    while True:
        line = await reader.readline()
        if not line:
            break
        parts = line.decode(errors='ignore').strip().split(' ', 2)
        if len(parts) < 2:
            continue
        tag, cmd = parts[0], parts[1].upper()
        rest = parts[2] if len(parts) > 2 else ''
        if cmd == 'LOGIN':
            writer.write(('%s OK LOGIN completed\r\n' % tag).encode())
        elif cmd == 'SELECT':
            writer.write(('* %d EXISTS\r\n%s OK [READ-WRITE] done\r\n' % (len(messages()), tag)).encode())
        elif cmd == 'UID' and rest.upper().startswith('SEARCH'):
            uids = [str(u) for u in messages() if u not in seen]
            writer.write(('* SEARCH %s\r\n%s OK done\r\n' % (' '.join(uids), tag)).encode())
        elif cmd == 'UID' and rest.upper().startswith('FETCH'):
            uid = int(rest.split()[1])
            body = open(messages()[uid], 'rb').read()
            writer.write(('* 1 FETCH (UID %d BODY[] {%d}\r\n' % (uid, len(body))).encode() + body + (')\r\n%s OK FETCH done\r\n' % tag).encode())
        elif cmd == 'UID' and rest.upper().startswith('STORE'):
            seen.add(int(rest.split()[1]))
            writer.write(('%s OK STORE done\r\n' % tag).encode())
        elif cmd == 'LOGOUT':
            writer.write(('* BYE\r\n%s OK bye\r\n' % tag).encode()); await writer.drain(); break
        else:
            writer.write(('%s OK\r\n' % tag).encode())
        await writer.drain()
    writer.close()


async def main():
    s1 = await asyncio.start_server(smtp, '127.0.0.1', smtp_port)
    s2 = await asyncio.start_server(imap, '127.0.0.1', imap_port)
    open(os.path.join(root, 'ready'), 'w').write('1')
    async with s1, s2:
        await asyncio.gather(s1.serve_forever(), s2.serve_forever())

asyncio.run(main())
