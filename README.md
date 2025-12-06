# MoneyMove API

A modern, multilingual financial API built with Symfony 7.4 for secure money transfers and account management.

## Features

- 🔐 **JWT Authentication** with secure token-based access
- 🌍 **Multilingual Support** (English, Spanish, French, Hindi)
- 💰 **Account Management** with encrypted data storage
- 💸 **Transaction Processing** with comprehensive ledger system
- 🔒 **Security First** with encrypted sensitive data
- 🧪 **Comprehensive Testing** (Unit, Integration, Functional)
- 📊 **GraphQL & REST APIs** for flexible data access
- ⚡ **Redis Caching** for optimal performance

## Prerequisites

Before installing the MoneyMove API, ensure you have the following installed:

- **PHP 8.3** or higher
- **Symfony 7.4** or higher
- **MySQL 8.0** or higher
- **Redis Server** for caching
- **Composer** for dependency management
- **Git** for version control

## Installation Steps

### 1. Clone the Repository

```bash
git clone -b development https://github.com/maninder1516/moneymoveapi.git moneymoveapi
```

### 2. Install Dependencies

```bash
cd moneymoveapi && composer install
```

### 3. Generate Cryptographic Keys

Generate encryption keys for secure configuration storage:

```bash
bin/console secrets:generate-keys
```

### 4. Configure Database Connection

Set your MySQL database connection string:

```bash
bin/console secrets:set DATABASE_URL
```

When prompted, enter your database URL in this format:
```
mysql://username:password@127.0.0.1:3306/moneymove_db?serverVersion=8.0&charset=utf8mb4
```

### 5. Configure Redis Connection

Set your Redis server connection:

```bash
bin/console secrets:set REDIS_URL
```

When prompted, enter your Redis URL (typically):
```
redis://localhost:6379
```

### 6. Generate JWT Key Pair

Create the cryptographic keys for JWT token authentication:

```bash
bin/console lexik:jwt:generate-keypair
```

### 7. Create Database Schema

Run the database migrations to create the required tables:

```bash
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate
```

### 8. Load Sample Data (Optional)

Load sample data for testing:

```bash
bin/console doctrine:fixtures:load
```

## Configuration

### Environment Variables

The application uses Symfony's secrets management for secure configuration. Key settings include:

- `DATABASE_URL` - MySQL connection string
- `REDIS_URL` - Redis server connection
- `JWT_PASSPHRASE` - Automatically generated during key pair creation

### Supported Languages

The API supports automatic language detection and responses in:

- **English** (en) - Default
- **Spanish** (es) - Español
- **French** (fr) - Français
- **Hindi** (hi) - हिंदी

## API Endpoints

### Authentication
- `POST /api/v1/auth/login` - User authentication
- `POST /api/v1/auth/register` - User registration

### Account Management
- `GET /api/v1/accounts` - List user accounts
- `GET /api/v1/accounts/{id}` - Get account details
- `POST /api/v1/accounts` - Create new account

### Transactions
- `POST /api/v1/transactions` - Create transaction
- `GET /api/v1/transactions` - List transactions

### GraphQL
- `POST /api/v1/graphql` - GraphQL endpoint
- `GET /api/v1/graphql/schema` - GraphQL schema

## Testing

The project includes comprehensive testing:

### Run All Tests
```bash
vendor/bin/phpunit
```

### Test Suites
- **Unit Tests** - Individual component testing
- **Integration Tests** - Service integration testing  
- **Functional Tests** - End-to-end API testing

### Current Test Status
- ✅ **Unit Tests**: 26/26 passing (100%)
- ⚠️ **Integration & Functional**: 43/48 passing (89.6%)

## Development

### Clear Cache
```bash
bin/console cache:clear
```

### Check Routes
```bash
bin/console debug:router
```

### Database Schema Update
```bash
bin/console doctrine:schema:update --force
```

## Security Features

- **JWT Token Authentication** with RS256 encryption
- **Database Field Encryption** for sensitive data
- **Redis Session Management** with secure caching
- **Input Validation** with comprehensive sanitization
- **CORS Protection** with configurable origins

## API Documentation

### Language Support
Add `?lang=es` to any endpoint for Spanish responses, or use HTTP headers:
```
Accept-Language: es,en;q=0.9
```

### Authentication
Include JWT token in requests:
```
Authorization: Bearer <your-jwt-token>
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests: `vendor/bin/phpunit`
5. Submit a pull request

## License

This project is licensed under the MIT License.

## Support

For issues and questions:
- Create an issue on GitHub
- Check the test suite for examples
- Review the API documentation

---

**MoneyMove API** - Secure, multilingual financial transactions made simple. 🚀