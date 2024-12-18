# luka-lta-api

This is the official API for the projects: [luka-lta-frontend](), [luka-lta-backend](https://github.com/luka-lta/luka-lta-backend)

## Requirements:

- PHP 8.3 or higher
- Docker
- PHP-redis-extension 8.3 or higher

## Endpoints:

Default: `/api/v1/`

### Authentication:
- `POST /auth` | Authenticate user and retrieve an access token

### API Keys:
- `POST /key/create` | Generate a new API key (requires authentication)

### LinkCollection:
- `GET /linkCollection/links` | Get all available links (requires authentication)
- `POST /linkCollection/create` | Create a new link (requires authentication)
- `PUT /linkCollection/{linkId}` | Edit an existing link (requires authentication)
- `DELETE /linkCollection/{linkId}` | Disable an existing link (requires authentication)

### Clicks:
- `GET /click/track` | Track a click event
- `GET /click/all` | Get all click events (requires authentication)

### User Management:
- `POST /user/create` | Create a new user
- `POST /user/{userId}` | Update an existing user (requires authentication)

## Setup Instructions

### Installation:
1. Clone the repository:
   ```bash
   git clone https://github.com/luka-lta/luka-lta-backend.git
   cd luka-lta-backend
   ```

2. Install dependencies using Composer:
   ```bash
   composer install
   ```

3. Start the application using Docker:
   ```bash
   docker-compose up -d
   ```

4. Run database migrations:
   ```bash
   php artisan migrate
   ```

### Testing the API:
- Use tools like [Postman](https://www.postman.com/) or [cURL](https://curl.se/) to test the endpoints.


