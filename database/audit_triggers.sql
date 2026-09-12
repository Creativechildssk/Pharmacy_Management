DELIMITER $$

CREATE TRIGGER audit_dispense_insert
AFTER INSERT ON dispenses
FOR EACH ROW
BEGIN
  INSERT INTO audit_logs(user_id,action,module,record_type,record_id,new_values)
  VALUES(
    NEW.pharmacist_id,
    'POST',
    'DISPENSING',
    'dispense',
    NEW.id,
    JSON_OBJECT('dispense_no',NEW.dispense_no,'patient_type',NEW.patient_type,'prescription_id',NEW.prescription_id,'status',NEW.status)
  );
END$$

CREATE TRIGGER audit_dispense_update
AFTER UPDATE ON dispenses
FOR EACH ROW
BEGIN
  IF OLD.status <> NEW.status THEN
    INSERT INTO audit_logs(user_id,action,module,record_type,record_id,old_values,new_values)
    VALUES(
      COALESCE(NEW.reversed_by,NEW.pharmacist_id),
      'STATUS_CHANGE',
      'DISPENSING',
      'dispense',
      NEW.id,
      JSON_OBJECT('status',OLD.status),
      JSON_OBJECT('status',NEW.status)
    );
  END IF;
END$$

CREATE TRIGGER audit_adjustment_insert
AFTER INSERT ON stock_adjustments
FOR EACH ROW
BEGIN
  INSERT INTO audit_logs(user_id,action,module,record_type,record_id,new_values)
  VALUES(
    NEW.created_by,
    'POST',
    'INVENTORY',
    'stock_adjustment',
    NEW.id,
    JSON_OBJECT('type',NEW.adjustment_type,'batch_id',NEW.batch_id,'quantity',NEW.quantity,'reason',NEW.reason,'status',NEW.status)
  );
END$$

CREATE TRIGGER audit_adjustment_update
AFTER UPDATE ON stock_adjustments
FOR EACH ROW
BEGIN
  IF OLD.status <> NEW.status THEN
    INSERT INTO audit_logs(user_id,action,module,record_type,record_id,old_values,new_values)
    VALUES(
      COALESCE(NEW.reversed_by,NEW.created_by),
      'STATUS_CHANGE',
      'INVENTORY',
      'stock_adjustment',
      NEW.id,
      JSON_OBJECT('status',OLD.status),
      JSON_OBJECT('status',NEW.status)
    );
  END IF;
END$$

CREATE TRIGGER audit_prescription_insert
AFTER INSERT ON prescriptions
FOR EACH ROW
BEGIN
  INSERT INTO audit_logs(user_id,action,module,record_type,record_id,new_values)
  VALUES(
    NEW.entered_by,
    'CREATE',
    'PRESCRIPTIONS',
    'prescription',
    NEW.id,
    JSON_OBJECT('prescription_no',NEW.prescription_no,'patient_type',NEW.patient_type,'doctor_id',NEW.doctor_id,'visit_date',NEW.visit_date,'status',NEW.status)
  );
END$$

DELIMITER ;
