CREATE TABLE `preview_access_tokens`
(
    `token`      varchar(6) NOT NULL,
    `created_by` int        NOT NULL,
    `created_at` datetime   NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_0900_ai_ci;

ALTER TABLE `preview_access_tokens`
    ADD PRIMARY KEY (`token`),
    ADD KEY `created_by_preview` (`created_by`);

