# luka-lta-api

This is the official API for the projects: [luka-lta-frontend](), [luka-lta-backend](https://github.com/luka-lta/luka-lta-backend)


## Requirements:


- PHP 8.3 or higher
- Docker 
- PHP-redis-extension 8.3 or higher

## Endpoints:

Default: `/api/v1/`

### LinkCollection:
- `GET /linkCollection/links` | Get all available links
- `POST /linkCollection/create` | Create a new link
- `PUT /linkCollection/{linkId}` | Edit an existing link
- `DELETE /linkCollection/{linkId}` | Disable an existing link