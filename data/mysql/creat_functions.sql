DELIMITER //

CREATE FUNCTION domainWithoutWWW(input TEXT)
    RETURNS TEXT
    DETERMINISTIC
BEGIN
    DECLARE result TEXT;

    SET result = LOWER(input);

    -- http/https entfernen
    SET result = REPLACE(result, 'http://', '');
    SET result = REPLACE(result, 'https://', '');

    -- www entfernen
    IF LEFT(result, 4) = 'www.' THEN
        SET result = SUBSTRING(result, 5);
    END IF;

    -- alles nach erstem / abschneiden
    IF LOCATE('/', result) > 0 THEN
        SET result = LEFT(result, LOCATE('/', result) - 1);
    END IF;

    RETURN result;
END //

DELIMITER ;
