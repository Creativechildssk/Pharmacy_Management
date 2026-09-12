SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(user_id, role_id),
  CONSTRAINT fk_user_roles_user FOREIGN KEY(user_id) REFERENCES users(id),
  CONSTRAINT fk_user_roles_role FOREIGN KEY(role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employees (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  division VARCHAR(120) NULL,
  department VARCHAR(120) NULL,
  designation VARCHAR(120) NULL,
  phone VARCHAR(30) NULL,
  employment_status VARCHAR(40) NOT NULL DEFAULT 'ACTIVE',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_employee_lookup(employee_code, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE external_patients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  date_of_birth DATE NULL,
  age SMALLINT UNSIGNED NULL,
  gender VARCHAR(30) NULL,
  phone VARCHAR(30) NULL,
  address TEXT NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE doctors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  specialization VARCHAR(120) NULL,
  registration_no VARCHAR(80) NULL,
  phone VARCHAR(30) NULL,
  visiting_schedule VARCHAR(150) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE medicine_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE medicines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medicine_code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  generic_name VARCHAR(180) NULL,
  strength VARCHAR(80) NULL,
  dosage_form VARCHAR(80) NULL,
  manufacturer VARCHAR(180) NULL,
  category_id BIGINT UNSIGNED NULL,
  unit_of_measure VARCHAR(40) NOT NULL,
  barcode VARCHAR(100) NULL UNIQUE,
  hsn_code VARCHAR(30) NULL,
  gst_rate DECIMAL(5,2) NULL,
  reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_medicines_category FOREIGN KEY(category_id) REFERENCES medicine_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE suppliers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  address TEXT NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(180) NULL,
  gst_no VARCHAR(30) NULL,
  contact_person VARCHAR(150) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_receipts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(50) NOT NULL UNIQUE,
  supplier_id BIGINT UNSIGNED NOT NULL,
  supplier_invoice_no VARCHAR(100) NOT NULL,
  invoice_date DATE NOT NULL,
  receipt_date DATE NOT NULL,
  status ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
  remarks TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  posted_by BIGINT UNSIGNED NULL,
  posted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_receipt_created_by FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_receipt_posted_by FOREIGN KEY(posted_by) REFERENCES users(id),
  INDEX idx_receipt_date_supplier(receipt_date, supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_receipt_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_id BIGINT UNSIGNED NOT NULL,
  medicine_id BIGINT UNSIGNED NOT NULL,
  batch_no VARCHAR(100) NOT NULL,
  manufacture_date DATE NULL,
  expiry_date DATE NOT NULL,
  received_quantity DECIMAL(12,3) NOT NULL,
  free_quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
  purchase_rate DECIMAL(12,4) NOT NULL,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  line_value DECIMAL(14,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipt_item_receipt FOREIGN KEY(receipt_id) REFERENCES stock_receipts(id),
  CONSTRAINT fk_receipt_item_medicine FOREIGN KEY(medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE medicine_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  medicine_id BIGINT UNSIGNED NOT NULL,
  batch_no VARCHAR(100) NOT NULL,
  manufacture_date DATE NULL,
  expiry_date DATE NOT NULL,
  purchase_rate DECIMAL(12,4) NOT NULL,
  mrp DECIMAL(12,2) NULL,
  supplier_id BIGINT UNSIGNED NULL,
  receipt_item_id BIGINT UNSIGNED NULL,
  quantity_received DECIMAL(12,3) NOT NULL DEFAULT 0,
  quantity_available DECIMAL(12,3) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','EXPIRED','DEPLETED','BLOCKED') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_batch_medicine FOREIGN KEY(medicine_id) REFERENCES medicines(id),
  CONSTRAINT fk_batch_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_batch_receipt_item FOREIGN KEY(receipt_item_id) REFERENCES stock_receipt_items(id),
  CONSTRAINT uq_batch_identity UNIQUE(medicine_id, batch_no, expiry_date),
  CHECK (quantity_available >= 0),
  INDEX idx_batch_fefo(medicine_id, expiry_date, quantity_available, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE prescriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prescription_no VARCHAR(60) NOT NULL UNIQUE,
  patient_type ENUM('EMPLOYEE','EXTERNAL') NOT NULL,
  employee_id BIGINT UNSIGNED NULL,
  external_patient_id BIGINT UNSIGNED NULL,
  doctor_id BIGINT UNSIGNED NOT NULL,
  visit_date DATE NOT NULL,
  diagnosis_notes TEXT NULL,
  prescription_notes TEXT NULL,
  status ENUM('OPEN','PARTIALLY_DISPENSED','DISPENSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
  entered_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_prescription_employee FOREIGN KEY(employee_id) REFERENCES employees(id),
  CONSTRAINT fk_prescription_external FOREIGN KEY(external_patient_id) REFERENCES external_patients(id),
  CONSTRAINT fk_prescription_doctor FOREIGN KEY(doctor_id) REFERENCES doctors(id),
  CONSTRAINT fk_prescription_user FOREIGN KEY(entered_by) REFERENCES users(id),
  CHECK ((patient_type='EMPLOYEE' AND employee_id IS NOT NULL AND external_patient_id IS NULL) OR (patient_type='EXTERNAL' AND employee_id IS NULL AND external_patient_id IS NOT NULL)),
  INDEX idx_prescription_doctor_date(doctor_id, visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE prescription_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prescription_id BIGINT UNSIGNED NOT NULL,
  medicine_id BIGINT UNSIGNED NOT NULL,
  dosage_instruction VARCHAR(255) NULL,
  frequency VARCHAR(80) NULL,
  duration VARCHAR(80) NULL,
  quantity_prescribed DECIMAL(12,3) NOT NULL,
  quantity_dispensed DECIMAL(12,3) NOT NULL DEFAULT 0,
  remarks TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prescription_item_header FOREIGN KEY(prescription_id) REFERENCES prescriptions(id),
  CONSTRAINT fk_prescription_item_medicine FOREIGN KEY(medicine_id) REFERENCES medicines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dispenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispense_no VARCHAR(60) NOT NULL UNIQUE,
  prescription_id BIGINT UNSIGNED NULL,
  patient_type ENUM('EMPLOYEE','EXTERNAL') NOT NULL,
  employee_id BIGINT UNSIGNED NULL,
  external_patient_id BIGINT UNSIGNED NULL,
  dispense_at DATETIME NOT NULL,
  status ENUM('POSTED','REVERSED') NOT NULL DEFAULT 'POSTED',
  pharmacist_id BIGINT UNSIGNED NOT NULL,
  remarks TEXT NULL,
  reversed_by BIGINT UNSIGNED NULL,
  reversed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dispense_prescription FOREIGN KEY(prescription_id) REFERENCES prescriptions(id),
  CONSTRAINT fk_dispense_employee FOREIGN KEY(employee_id) REFERENCES employees(id),
  CONSTRAINT fk_dispense_external FOREIGN KEY(external_patient_id) REFERENCES external_patients(id),
  CONSTRAINT fk_dispense_pharmacist FOREIGN KEY(pharmacist_id) REFERENCES users(id),
  CONSTRAINT fk_dispense_reversed_by FOREIGN KEY(reversed_by) REFERENCES users(id),
  CHECK ((patient_type='EMPLOYEE' AND employee_id IS NOT NULL AND external_patient_id IS NULL) OR (patient_type='EXTERNAL' AND employee_id IS NULL AND external_patient_id IS NOT NULL)),
  INDEX idx_dispense_date_type(dispense_at, patient_type),
  INDEX idx_dispense_employee(employee_id, dispense_at),
  INDEX idx_dispense_external(external_patient_id, dispense_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dispense_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispense_id BIGINT UNSIGNED NOT NULL,
  medicine_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  prescription_item_id BIGINT UNSIGNED NULL,
  quantity DECIMAL(12,3) NOT NULL,
  unit_cost DECIMAL(12,4) NOT NULL,
  total_cost DECIMAL(14,2) NOT NULL,
  dosage_instruction VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dispense_item_header FOREIGN KEY(dispense_id) REFERENCES dispenses(id),
  CONSTRAINT fk_dispense_item_medicine FOREIGN KEY(medicine_id) REFERENCES medicines(id),
  CONSTRAINT fk_dispense_item_batch FOREIGN KEY(batch_id) REFERENCES medicine_batches(id),
  CONSTRAINT fk_dispense_item_prescription FOREIGN KEY(prescription_item_id) REFERENCES prescription_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_type ENUM('RECEIPT','DISPENSE','PATIENT_RETURN','SUPPLIER_RETURN','DAMAGE','EXPIRED','ADJUSTMENT_IN','ADJUSTMENT_OUT','OPENING_BALANCE','REVERSAL') NOT NULL,
  medicine_id BIGINT UNSIGNED NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  quantity_in DECIMAL(12,3) NOT NULL DEFAULT 0,
  quantity_out DECIMAL(12,3) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(12,4) NOT NULL,
  transaction_value DECIMAL(14,2) NOT NULL,
  source_type VARCHAR(60) NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  reason TEXT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_stock_tx_medicine FOREIGN KEY(medicine_id) REFERENCES medicines(id),
  CONSTRAINT fk_stock_tx_batch FOREIGN KEY(batch_id) REFERENCES medicine_batches(id),
  CONSTRAINT fk_stock_tx_user FOREIGN KEY(user_id) REFERENCES users(id),
  CHECK (quantity_in >= 0),
  CHECK (quantity_out >= 0),
  INDEX idx_stock_tx_batch_time(medicine_id, batch_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_adjustments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  adjustment_no VARCHAR(60) NOT NULL UNIQUE,
  adjustment_type ENUM('DAMAGE','EXPIRED','SUPPLIER_RETURN','ADJUSTMENT_IN','ADJUSTMENT_OUT') NOT NULL,
  batch_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('POSTED','REVERSED') NOT NULL DEFAULT 'POSTED',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reversed_by BIGINT UNSIGNED NULL,
  reversed_at DATETIME NULL,
  CONSTRAINT fk_adjustment_batch FOREIGN KEY(batch_id) REFERENCES medicine_batches(id),
  CONSTRAINT fk_adjustment_created_by FOREIGN KEY(created_by) REFERENCES users(id),
  CONSTRAINT fk_adjustment_reversed_by FOREIGN KEY(reversed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  module VARCHAR(80) NOT NULL,
  record_type VARCHAR(80) NOT NULL,
  record_id BIGINT UNSIGNED NULL,
  old_values JSON NULL,
  new_values JSON NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE system_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY(updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL,
  ip_address VARCHAR(64) NULL,
  successful TINYINT(1) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempts(username, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;
