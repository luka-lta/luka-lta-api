CREATE TABLE url_clicks
(
    click_id         INT AUTO_INCREMENT PRIMARY KEY,
    url        TEXT        NOT NULL,
    clicked_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT        NULL,
    referrer   TEXT        NULL
);
