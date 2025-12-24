SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('SuperAdmin','Admin','Operator') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_username_unique (username),
  KEY users_role_idx (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(120) NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY suppliers_name_idx (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS material_types (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY material_types_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS units (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY units_code_unique (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  product_code VARCHAR(80) NULL,
  material_type_id BIGINT UNSIGNED NOT NULL,
  supplier_id BIGINT UNSIGNED NULL,
  unit_id BIGINT UNSIGNED NOT NULL,
  current_qty DECIMAL(14,4) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  purchase_date DATE NULL,
  purchase_url VARCHAR(500) NULL,
  min_stock DECIMAL(14,4) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY materials_name_idx (name),
  KEY materials_archived_idx (is_archived),
  KEY materials_type_idx (material_type_id),
  CONSTRAINT fk_materials_type FOREIGN KEY (material_type_id) REFERENCES material_types(id),
  CONSTRAINT fk_materials_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_materials_unit FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS material_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  material_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('in','out','adjust') NOT NULL,
  qty DECIMAL(14,4) NOT NULL,
  unit_cost DECIMAL(14,4) NULL,
  ref_type VARCHAR(40) NULL,
  ref_id BIGINT UNSIGNED NULL,
  note TEXT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY movements_material_idx (material_id),
  KEY movements_user_idx (user_id),
  KEY movements_created_idx (created_at),
  CONSTRAINT fk_movements_material FOREIGN KEY (material_id) REFERENCES materials(id),
  CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS machines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  power_kw DECIMAL(10,4) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY machines_name_unique (name),
  KEY machines_active_idx (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY product_categories_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  sku VARCHAR(60) NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  sale_price DECIMAL(14,4) NOT NULL DEFAULT 0,
  estimated_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
  manpower_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('In productie','Finalizat','Vandut') NOT NULL DEFAULT 'In productie',
  stock_qty INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY products_sku_unique (sku),
  KEY products_category_idx (category_id),
  KEY products_status_idx (status),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bom_materials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(14,4) NOT NULL,
  unit_id BIGINT UNSIGNED NOT NULL,
  waste_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY bom_materials_unique (product_id, material_id),
  KEY bom_materials_product_idx (product_id),
  KEY bom_materials_material_idx (material_id),
  CONSTRAINT fk_bom_materials_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_bom_materials_material FOREIGN KEY (material_id) REFERENCES materials(id),
  CONSTRAINT fk_bom_materials_unit FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bom_machines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  machine_id BIGINT UNSIGNED NOT NULL,
  hours DECIMAL(10,4) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY bom_machines_unique (product_id, machine_id),
  KEY bom_machines_product_idx (product_id),
  KEY bom_machines_machine_idx (machine_id),
  CONSTRAINT fk_bom_machines_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_bom_machines_machine FOREIGN KEY (machine_id) REFERENCES machines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  status ENUM('Pornita','Finalizata','Anulata') NOT NULL DEFAULT 'Pornita',
  started_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  operator_user_id BIGINT UNSIGNED NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY production_product_idx (product_id),
  KEY production_status_idx (status),
  KEY production_started_idx (started_at),
  CONSTRAINT fk_production_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_production_operator FOREIGN KEY (operator_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_material_usage (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  production_order_id BIGINT UNSIGNED NOT NULL,
  material_id BIGINT UNSIGNED NOT NULL,
  qty_used DECIMAL(14,4) NOT NULL,
  unit_cost DECIMAL(14,4) NOT NULL,
  cost DECIMAL(14,4) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY prod_mat_usage_order_idx (production_order_id),
  KEY prod_mat_usage_material_idx (material_id),
  CONSTRAINT fk_prod_mat_usage_order FOREIGN KEY (production_order_id) REFERENCES production_orders(id),
  CONSTRAINT fk_prod_mat_usage_material FOREIGN KEY (material_id) REFERENCES materials(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_machine_usage (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  production_order_id BIGINT UNSIGNED NOT NULL,
  machine_id BIGINT UNSIGNED NOT NULL,
  hours_used DECIMAL(10,4) NOT NULL,
  power_kw DECIMAL(10,4) NOT NULL,
  energy_kwh DECIMAL(14,4) NOT NULL,
  cost DECIMAL(14,4) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY prod_machine_usage_order_idx (production_order_id),
  KEY prod_machine_usage_machine_idx (machine_id),
  CONSTRAINT fk_prod_machine_usage_order FOREIGN KEY (production_order_id) REFERENCES production_orders(id),
  CONSTRAINT fk_prod_machine_usage_machine FOREIGN KEY (machine_id) REFERENCES machines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_costs (
  production_order_id BIGINT UNSIGNED NOT NULL,
  materials_cost DECIMAL(14,4) NOT NULL,
  energy_cost DECIMAL(14,4) NOT NULL,
  manpower_cost DECIMAL(14,4) NOT NULL,
  total_cost DECIMAL(14,4) NOT NULL,
  cost_per_unit DECIMAL(14,4) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (production_order_id),
  CONSTRAINT fk_production_costs_order FOREIGN KEY (production_order_id) REFERENCES production_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id BIGINT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  sale_price DECIMAL(14,4) NOT NULL,
  sold_at DATETIME NOT NULL,
  customer_name VARCHAR(160) NULL,
  channel VARCHAR(80) NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY sales_sold_idx (sold_at),
  KEY sales_product_idx (product_id),
  CONSTRAINT fk_sales_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
  ('energy_cost_per_kwh', '1.00'),
  ('operator_hourly_cost', '0.00'),
  ('timezone', 'Europe/Bucharest'),
  ('language', 'ro'),
  ('currency', 'lei');

INSERT IGNORE INTO units (id, code, name, created_at, updated_at) VALUES
  (1, 'buc', 'Bucata', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (2, 'kg', 'Kilogram', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (3, 'g', 'Gram', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (4, 'm', 'Metru', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (5, 'cm', 'Centimetru', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (6, 'ml', 'Mililitru', UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (7, 'l', 'Litru', UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT IGNORE INTO material_types (id, name, is_active, created_at, updated_at) VALUES
  (1, 'Lemn', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (2, 'Metal', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (3, 'Electric', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (4, 'Consumabile', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT IGNORE INTO product_categories (id, name, is_active, created_at, updated_at) VALUES
  (1, 'Lampi', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (2, 'Lustre', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (3, 'Veioze', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (4, 'Suporturi lumanari', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());

