CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(120) NOT NULL PRIMARY KEY,
  svalue TEXT
){ENGINE};

CREATE TABLE IF NOT EXISTS users (
  id {PK},
  email VARCHAR(190) NOT NULL,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(190) NOT NULL DEFAULT '',
  phone VARCHAR(60) NOT NULL DEFAULT '',
  role VARCHAR(20) NOT NULL DEFAULT 'admin',
  created_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_users_email ON users (email);

CREATE TABLE IF NOT EXISTS login_attempts (
  id {PK},
  ip VARCHAR(64) NOT NULL,
  created_at INTEGER NOT NULL
){ENGINE};
CREATE INDEX idx_la_ip ON login_attempts (ip, created_at);

CREATE TABLE IF NOT EXISTS nodes (
  id {PK},
  name VARCHAR(120) NOT NULL,
  role VARCHAR(12) NOT NULL,
  token_hash VARCHAR(64) NOT NULL,
  address VARCHAR(190) NOT NULL DEFAULT '',
  upstream VARCHAR(190) NOT NULL DEFAULT '',
  trusted VARCHAR(190) NOT NULL DEFAULT '',
  tunnel_host VARCHAR(190) NOT NULL DEFAULT '',
  relay_secret VARCHAR(64) NOT NULL DEFAULT '',
  hestia_user VARCHAR(60) NOT NULL DEFAULT '',
  info TEXT,
  last_seen VARCHAR(19),
  created_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_nodes_token ON nodes (token_hash);

CREATE TABLE IF NOT EXISTS shops (
  id {PK},
  name VARCHAR(190) NOT NULL,
  domain VARCHAR(190) NOT NULL,
  path VARCHAR(60) NOT NULL DEFAULT '',
  node_id INTEGER NOT NULL,
  edge_node_id INTEGER NOT NULL DEFAULT 0,
  hestia_user VARCHAR(60) NOT NULL DEFAULT '',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  admin_email VARCHAR(190) NOT NULL DEFAULT '',
  admin_password TEXT,
  theme VARCHAR(40) NOT NULL DEFAULT 'aurora',
  lang VARCHAR(5) NOT NULL DEFAULT 'it',
  demo INTEGER NOT NULL DEFAULT 0,
  secret TEXT,
  metrics TEXT,
  version VARCHAR(40) NOT NULL DEFAULT '',
  last_seen VARCHAR(19),
  last_ok VARCHAR(19),
  last_error VARCHAR(500) NOT NULL DEFAULT '',
  notes TEXT,
  created_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_shops_target ON shops (domain, path);

CREATE TABLE IF NOT EXISTS jobs (
  id {PK},
  node_id INTEGER NOT NULL,
  shop_id INTEGER NOT NULL DEFAULT 0,
  type VARCHAR(30) NOT NULL,
  payload TEXT,
  status VARCHAR(12) NOT NULL DEFAULT 'queued',
  result TEXT,
  log TEXT,
  created_at VARCHAR(19) NOT NULL,
  started_at VARCHAR(19),
  finished_at VARCHAR(19)
){ENGINE};
CREATE INDEX idx_jobs_node ON jobs (node_id, status);
