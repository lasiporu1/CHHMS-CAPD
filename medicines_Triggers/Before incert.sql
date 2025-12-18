CREATE DEFINER=`root`@`localhost` TRIGGER prevent_duplicate_active_medicine_insert
BEFORE INSERT ON medicines
FOR EACH ROW
BEGIN
    IF NEW.status = 'Active' AND NEW.medicine_master_id IS NOT NULL AND NEW.medicine_master_id <> 0 AND NEW.patient_id IS NOT NULL THEN
        IF (SELECT COUNT(*) FROM medicines m WHERE m.patient_id = NEW.patient_id AND m.medicine_master_id = NEW.medicine_master_id AND m.status = 'Active') > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Active prescription for this patient and medicine already exists';
        END IF;
    END IF;
END