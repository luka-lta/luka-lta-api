# luka-lta-api

[![Contributors][contributors-shield]][contributors-url]
[![Forks][forks-shield]][forks-url]
[![Stargazers][stars-shield]][stars-url]
[![Issues][issues-shield]][issues-url]
[![License][license-shield]][license-url]
[![LinkedIn][linkedin-shield]][linkedin-url]

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/luka-lta/luka-lta-api">
    <img src="images/logo.webp" alt="Logo" width="80" height="80">
  </a>

<h3 align="center">luka-lta-api</h3>

  <p align="center">
    Backend API for managing links and clicks in my system.
    <br />
    <a href="https://github.com/luka-lta/luka-lta-api"><strong>Explore the docs »</strong></a>
    <br />
    <br />
    <a href="https://luka-lta.dev/">View Demo</a>
    &middot;
    <a href="https://github.com/luka-lta/luka-lta-api/issues/new?labels=bug&template=bug-report---.md">Report Bug</a>
    &middot;
    <a href="https://github.com/luka-lta/luka-lta-api/issues/new?labels=enhancement&template=feature-request---.md">Request Feature</a>
  </p>
</div>

---

## About The Project

This backend API is built with PHP and the Slim framework. It manages link collections, tracks click events, and supports API key authentication for secure access.

### Built With

- ![PHP][php]
- [Slim Framework](https://www.slimframework.com/)
- Docker
- MySQL

## Getting Started

### Prerequisites

Ensure you have the following installed:

- PHP 8.3 or higher
- Docker
- PHP Redis extension 8.3 or higher

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/luka-lta/luka-lta-api.git
   cd luka-lta-api
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

### Testing the API

Use tools like [Postman](https://www.postman.com/) or [cURL](https://curl.se/) to test the endpoints.

---

## API Endpoints

### Authentication
- `POST /auth` – Authenticate user and retrieve an access token

### API Keys
- `POST /key/create` – Generate a new API key (requires authentication)

### Link Collection
- `GET /linkCollection/links` – Get all available links (requires authentication)
- `POST /linkCollection/create` – Create a new link (requires authentication)
- `PUT /linkCollection/{linkId}` – Edit an existing link (requires authentication)
- `DELETE /linkCollection/{linkId}` – Disable an existing link (requires authentication)

### Click Tracking
- `GET /click/track` – Track a click event
- `GET /click/all` – Get all click events (requires authentication)

### User Management
- `POST /user/create` – Create a new user
- `POST /user/{userId}` – Update an existing user (requires authentication)

---

## Roadmap

See the [open issues](https://github.com/luka-lta/luka-lta-api/issues) for a list of proposed features and known issues.

---

## Contributing

Contributions are what make the open-source community amazing! Any contributions you make are **greatly appreciated**.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a pull request

### Top Contributors

<a href="https://github.com/luka-lta/luka-lta-api/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=luka-lta/luka-lta-api" alt="Contributors" />
</a>

---

## License

Distributed under the MIT License. See `LICENSE.txt` for more information.

---

## Contact

**luka-lta** – [info@luka-lta.dev](mailto:info@luka-lta.dev)

Project Link: [https://github.com/luka-lta/luka-lta-api](https://github.com/luka-lta/luka-lta-api/)

---

## Acknowledgments

- [Slim Framework](https://www.slimframework.com/)
- [PHP](https://www.php.net/)
- [Docker](https://www.docker.com/)

---

[contributors-shield]: https://img.shields.io/github/contributors/luka-lta/luka-lta-api.svg?style=for-the-badge
[contributors-url]: https://github.com/luka-lta/luka-lta-api/graphs/contributors
[forks-shield]: https://img.shields.io/github/forks/luka-lta/luka-lta-api.svg?style=for-the-badge
[forks-url]: https://github.com/luka-lta/luka-lta-api/network/members
[stars-shield]: https://img.shields.io/github/stars/luka-lta/luka-lta-api.svg?style=for-the-badge
[stars-url]: https://github.com/luka-lta/luka-lta-api/stargazers
[issues-shield]: https://img.shields.io/github/issues/luka-lta/luka-lta-api.svg?style=for-the-badge
[issues-url]: https://github.com/luka-lta/luka-lta-api/issues
[license-shield]: https://img.shields.io/github/license/luka-lta/luka-lta-api.svg?style=for-the-badge
[license-url]: https://github.com/luka-lta/luka-lta-api/blob/master/LICENSE.txt
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://linkedin.com/in/linkedin_username
[php]: https://img.shields.io/badge/php-000000?style=for-the-badge&logo=php&logoColor=white

