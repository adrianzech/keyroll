# KeyRoll - SSH Key Management System

KeyRoll is a web application built with Symfony 7 that simplifies SSH key management and deployment across multiple servers. It allows system administrators to easily manage and deploy SSH keys to remote hosts.

![PHP Version](https://img.shields.io/badge/PHP-8.4-blue.svg)
![Symfony Version](https://img.shields.io/badge/Symfony-7.2-green.svg)
![License](https://img.shields.io/badge/License-MIT-blue.svg)

## 🚀 Features

- **SSH Key Management**: Create, store, and manage SSH public keys
- **Host Management**: Store and manage connection details for remote servers
- **Automated Key Deployment**: Deploy keys to multiple servers with a single command
- **User Authentication**: Secure user accounts and role-based access control
- **Docker Support**: Ready-to-use Docker setup for development and production
- **Modern UI**: Clean interface using Tailwind CSS v4 and DaisyUI

## 📋 Requirements

-   PHP 8.4 or higher
-   Composer
-   A supported database (e.g., MariaDB >= 10.5, MySQL >= 8.0)
-   Web Server (e.g., Nginx, Apache) or use the built-in Symfony server for development.
-   Node.js 22 or higher (Primarily for frontend asset building with Tailwind/DaisyUI)
-   npm (or yarn/pnpm)
-   Docker and Docker Compose (Recommended for standardized development and deployment)


## 🛠️ Installation

### Using Docker (Recommended)

1. Clone the repository:
   ```bash
   git clone https://github.com/adrianzech/keyroll.git
   cd keyroll
   ```

2. Copy environment configuration:
   ```bash
   cp docker/.env.dist docker/.env
   ```

3. Customize environment variables in `docker/.env`

4. Start the containers:
   ```bash
   cd docker
   docker compose up -d
   ```

5. Access KeyRoll at `http://localhost:9000` (or the port you configured)

### Manual Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/username/keyroll.git
   cd keyroll
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Install frontend dependencies:
   ```bash
   npm install
   ```

4. Configure your environment:
   ```bash
   cp .env .env.local
   ```
   Edit `.env.local` with your database settings and other configurations

5. Create database schema:
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

6. Generate SSH key for KeyRoll to use:
   ```bash
   php bin/console app:ssh-key:generate
   ```

7. Build frontend assets:
   ```bash
   php bin/console tailwind:build
   php bin/console asset-map:compile
   ```

8. Start the local development server:
   ```bash
   symfony server:start
   ```

## 🧑‍💻 Development

### Commands

- **Tailwind Compilation**:
  ```bash
  php bin/console tailwind:build --watch
  ```

- **Code Quality**:
  ```bash
  composer cs-check       # Run PHP CS Fixer (dry-run)
  composer cs-fix         # Run PHP CS Fixer and fix issues
  composer phpstan        # Run PHPStan static analysis
  composer phpmd          # Run PHP Mess Detector
  composer code-analysis  # Run all code quality tools
  ```

### Docker Development

The Docker setup includes:
- PHP 8.4 FPM container
- MariaDB 11.4 database
- Basic health checks and automatic migrations

## 🚀 Deployment

### Build Docker Image

The repository includes a CI/CD pipeline configuration for GitHub Actions that:
1. Runs all code quality checks
2. Builds a production-ready Docker image
3. Pushes the image to GitHub Container Registry
