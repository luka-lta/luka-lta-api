-- Current air quality (Open-Meteo air-quality API) next to the forecast cache.
-- NULL when the air quality request failed or returned no values.
-- Sorts after weather_readings.sql, which creates the table.
ALTER TABLE `weather_forecast_cache`
    ADD COLUMN `air_quality_payload` json NULL DEFAULT NULL AFTER `daily_payload`;
