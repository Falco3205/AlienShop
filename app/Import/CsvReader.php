<?php
declare(strict_types=1);

namespace Alien\Import;

final class CsvReader
{
    private array $headers = [];
    private array $normalized = [];
    private $handle;

    public function __construct(string $file)
    {
        $this->handle = fopen($file, 'rb');
        if (!$this->handle) {
            throw new \RuntimeException('File non leggibile.');
        }
        $bom = fread($this->handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($this->handle);
        }
        $firstLine = (string)fgets($this->handle);
        rewind($this->handle);
        if ($bom === "\xEF\xBB\xBF") {
            fread($this->handle, 3);
        }
        $delimiter = ',';
        $best = 0;
        foreach ([',', ';', "\t"] as $d) {
            $n = count(str_getcsv($firstLine, $d, '"', '\\'));
            if ($n > $best) {
                $best = $n;
                $delimiter = $d;
            }
        }
        $this->delimiter = $delimiter;
        $headers = fgetcsv($this->handle, 0, $delimiter, '"', '\\') ?: [];
        $this->headers = array_map(static fn($h) => trim((string)$h), $headers);
        foreach ($this->headers as $i => $h) {
            $this->normalized[$i] = self::norm($h);
        }
    }

    private string $delimiter = ',';

    public static function norm(string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $s) ?? $s));
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function rows(): \Generator
    {
        $line = 1;
        while (($cols = fgetcsv($this->handle, 0, $this->delimiter, '"', '\\')) !== false) {
            $line++;
            if ($cols === [null] || $cols === []) {
                continue;
            }
            $row = [];
            foreach ($this->normalized as $i => $key) {
                $row[$key] = isset($cols[$i]) ? trim((string)$cols[$i]) : '';
            }
            $row['__line'] = $line;
            yield $row;
        }
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
