INSERT INTO roles(code,name) VALUES
('ADMIN','Administrator'),
('PHARMACIST','Pharmacist'),
('INVENTORY','Store / Inventory User'),
('MANAGEMENT_VIEWER','Management Viewer');

INSERT INTO system_settings(setting_key,setting_value) VALUES
('estate_name','Estate Pharmacy'),
('near_expiry_days_1','30'),
('near_expiry_days_2','60'),
('near_expiry_days_3','90'),
('session_timeout_minutes','30');
