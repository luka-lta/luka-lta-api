dockerCompose := "docker compose -f docker-compose.development.yml"
containerRun  := dockerCompose + " run --rm php-fpm"

install:
    composer install

dev:
    {{dockerCompose}} up

stop:
    {{dockerCompose}} down

lint:
    {{containerRun}} vendor/bin/phpmd src text phpmd.xml
    {{containerRun}} vendor/bin/phpcs src

build ENV="development":
    DOCKER_BUILDKIT=1 COMPOSE_DOCKER_CLI_BUILD=1 docker compose -f docker-compose.{{ENV}}.yml build --pull
