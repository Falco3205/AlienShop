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
  role VARCHAR(20) NOT NULL DEFAULT 'customer',
  created_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_users_email ON users (email);

CREATE TABLE IF NOT EXISTS categories (
  id {PK},
  parent_id INTEGER NOT NULL DEFAULT 0,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description TEXT,
  image VARCHAR(255),
  seo_title VARCHAR(190) NOT NULL DEFAULT '',
  seo_description VARCHAR(320) NOT NULL DEFAULT '',
  position INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  show_in_menu INTEGER NOT NULL DEFAULT 1
){ENGINE};
CREATE UNIQUE INDEX idx_categories_slug ON categories (slug);
CREATE INDEX idx_categories_parent ON categories (parent_id);

CREATE TABLE IF NOT EXISTS products (
  id {PK},
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  type VARCHAR(20) NOT NULL DEFAULT 'simple',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  sku VARCHAR(100) NOT NULL DEFAULT '',
  short_description TEXT,
  description TEXT,
  price INTEGER NOT NULL DEFAULT 0,
  compare_price INTEGER NOT NULL DEFAULT 0,
  price_min INTEGER NOT NULL DEFAULT 0,
  price_max INTEGER NOT NULL DEFAULT 0,
  manage_stock INTEGER NOT NULL DEFAULT 0,
  stock_qty INTEGER NOT NULL DEFAULT 0,
  in_stock INTEGER NOT NULL DEFAULT 1,
  weight INTEGER NOT NULL DEFAULT 0,
  vendor VARCHAR(190) NOT NULL DEFAULT '',
  tags VARCHAR(500) NOT NULL DEFAULT '',
  image VARCHAR(255),
  seo_title VARCHAR(190) NOT NULL DEFAULT '',
  seo_description VARCHAR(320) NOT NULL DEFAULT '',
  noindex INTEGER NOT NULL DEFAULT 0,
  featured INTEGER NOT NULL DEFAULT 0,
  external_id VARCHAR(100) NOT NULL DEFAULT '',
  created_at VARCHAR(19) NOT NULL,
  updated_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_products_slug ON products (slug);
CREATE INDEX idx_products_status ON products (status, created_at);
CREATE INDEX idx_products_sku ON products (sku);
CREATE INDEX idx_products_price ON products (price_min);

CREATE TABLE IF NOT EXISTS product_categories (
  product_id INTEGER NOT NULL,
  category_id INTEGER NOT NULL,
  PRIMARY KEY (product_id, category_id)
){ENGINE};
CREATE INDEX idx_pc_category ON product_categories (category_id);

CREATE TABLE IF NOT EXISTS product_images (
  id {PK},
  product_id INTEGER NOT NULL,
  path VARCHAR(255) NOT NULL,
  alt VARCHAR(255) NOT NULL DEFAULT '',
  position INTEGER NOT NULL DEFAULT 0
){ENGINE};
CREATE INDEX idx_pi_product ON product_images (product_id, position);

CREATE TABLE IF NOT EXISTS product_attributes (
  id {PK},
  product_id INTEGER NOT NULL,
  name VARCHAR(120) NOT NULL,
  position INTEGER NOT NULL DEFAULT 0
){ENGINE};
CREATE INDEX idx_pa_product ON product_attributes (product_id);

CREATE TABLE IF NOT EXISTS attribute_values (
  id {PK},
  attribute_id INTEGER NOT NULL,
  value VARCHAR(190) NOT NULL,
  price_delta INTEGER NOT NULL DEFAULT 0,
  position INTEGER NOT NULL DEFAULT 0
){ENGINE};
CREATE INDEX idx_av_attribute ON attribute_values (attribute_id);

CREATE TABLE IF NOT EXISTS variants (
  id {PK},
  product_id INTEGER NOT NULL,
  sku VARCHAR(100) NOT NULL DEFAULT '',
  options TEXT NOT NULL,
  options_key VARCHAR(500) NOT NULL DEFAULT '',
  price INTEGER,
  compare_price INTEGER,
  stock INTEGER NOT NULL DEFAULT 0,
  image VARCHAR(255),
  position INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1
){ENGINE};
CREATE INDEX idx_variants_product ON variants (product_id);

CREATE TABLE IF NOT EXISTS redirects (
  id {PK},
  from_path VARCHAR(500) NOT NULL,
  to_path VARCHAR(500) NOT NULL,
  code INTEGER NOT NULL DEFAULT 301,
  hits INTEGER NOT NULL DEFAULT 0,
  created_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_redirects_from ON redirects (from_path);

CREATE TABLE IF NOT EXISTS pages (
  id {PK},
  type VARCHAR(10) NOT NULL DEFAULT 'page',
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  content TEXT,
  excerpt TEXT,
  image VARCHAR(255),
  seo_title VARCHAR(190) NOT NULL DEFAULT '',
  seo_description VARCHAR(320) NOT NULL DEFAULT '',
  show_in_menu INTEGER NOT NULL DEFAULT 0,
  show_in_footer INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at VARCHAR(19) NOT NULL,
  updated_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_pages_slug ON pages (type, slug);

CREATE TABLE IF NOT EXISTS coupons (
  id {PK},
  code VARCHAR(60) NOT NULL,
  type VARCHAR(10) NOT NULL DEFAULT 'percent',
  value INTEGER NOT NULL DEFAULT 0,
  min_subtotal INTEGER NOT NULL DEFAULT 0,
  max_uses INTEGER NOT NULL DEFAULT 0,
  used INTEGER NOT NULL DEFAULT 0,
  free_shipping INTEGER NOT NULL DEFAULT 0,
  starts_at VARCHAR(19),
  expires_at VARCHAR(19),
  active INTEGER NOT NULL DEFAULT 1
){ENGINE};
CREATE UNIQUE INDEX idx_coupons_code ON coupons (code);

CREATE TABLE IF NOT EXISTS shipping_methods (
  id {PK},
  name VARCHAR(190) NOT NULL,
  price INTEGER NOT NULL DEFAULT 0,
  free_over INTEGER NOT NULL DEFAULT 0,
  countries VARCHAR(500) NOT NULL DEFAULT '',
  position INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1
){ENGINE};

CREATE TABLE IF NOT EXISTS orders (
  id {PK},
  number VARCHAR(30) NOT NULL,
  token VARCHAR(64) NOT NULL,
  user_id INTEGER NOT NULL DEFAULT 0,
  email VARCHAR(190) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
  payment_method VARCHAR(40) NOT NULL DEFAULT '',
  payment_ref VARCHAR(190) NOT NULL DEFAULT '',
  currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
  subtotal INTEGER NOT NULL DEFAULT 0,
  discount INTEGER NOT NULL DEFAULT 0,
  shipping INTEGER NOT NULL DEFAULT 0,
  tax INTEGER NOT NULL DEFAULT 0,
  total INTEGER NOT NULL DEFAULT 0,
  coupon_code VARCHAR(60) NOT NULL DEFAULT '',
  shipping_method VARCHAR(190) NOT NULL DEFAULT '',
  billing TEXT,
  shipping_address TEXT,
  note TEXT,
  tracking VARCHAR(190) NOT NULL DEFAULT '',
  created_at VARCHAR(19) NOT NULL,
  updated_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_orders_number ON orders (number);
CREATE UNIQUE INDEX idx_orders_token ON orders (token);
CREATE INDEX idx_orders_user ON orders (user_id);
CREATE INDEX idx_orders_created ON orders (created_at);

CREATE TABLE IF NOT EXISTS order_items (
  id {PK},
  order_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL DEFAULT 0,
  variant_id INTEGER NOT NULL DEFAULT 0,
  name VARCHAR(255) NOT NULL,
  variant_label VARCHAR(255) NOT NULL DEFAULT '',
  sku VARCHAR(100) NOT NULL DEFAULT '',
  price INTEGER NOT NULL DEFAULT 0,
  qty INTEGER NOT NULL DEFAULT 1,
  total INTEGER NOT NULL DEFAULT 0
){ENGINE};
CREATE INDEX idx_oi_order ON order_items (order_id);

CREATE TABLE IF NOT EXISTS order_events (
  id {PK},
  order_id INTEGER NOT NULL,
  message VARCHAR(500) NOT NULL,
  created_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE INDEX idx_oe_order ON order_events (order_id);

CREATE TABLE IF NOT EXISTS login_attempts (
  id {PK},
  ip VARCHAR(64) NOT NULL,
  created_at INTEGER NOT NULL
){ENGINE};
CREATE INDEX idx_la_ip ON login_attempts (ip, created_at);

CREATE TABLE IF NOT EXISTS not_found_log (
  id {PK},
  path VARCHAR(500) NOT NULL,
  hits INTEGER NOT NULL DEFAULT 1,
  last_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_nf_path ON not_found_log (path);

CREATE TABLE IF NOT EXISTS product_stats (
  id {PK},
  day VARCHAR(10) NOT NULL,
  product_id INTEGER NOT NULL,
  views INTEGER NOT NULL DEFAULT 0,
  carts INTEGER NOT NULL DEFAULT 0,
  checkouts INTEGER NOT NULL DEFAULT 0
){ENGINE};
CREATE UNIQUE INDEX idx_ps_day_product ON product_stats (day, product_id);

CREATE TABLE IF NOT EXISTS search_terms (
  id {PK},
  term VARCHAR(190) NOT NULL,
  hits INTEGER NOT NULL DEFAULT 1,
  zero INTEGER NOT NULL DEFAULT 0,
  last_at VARCHAR(19) NOT NULL
){ENGINE};
CREATE UNIQUE INDEX idx_st_term ON search_terms (term);
