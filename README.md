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

### GraphQL (Future Scope)
- `POST /api/v1/graphql` - GraphQL endpoint *(planned)*
- `GET /api/v1/graphql/schema` - GraphQL schema *(planned)*

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

## Development Info

**Time spent**: ~12 hours

This project was developed with focus on:
- Modern Symfony 7.4 architecture
- Comprehensive testing implementation (PHPUnit)
- Multilingual API support (4 languages)
- Security-first approach (JWT, encryption, Redis)
- Clean code practices and documentation

## AI Tools and Prompts Used

This project was developed with assistance from **GitHub Copilot** and **ChatGPT**. Below are the key prompts and development phases:

### 1. **Directory Structure Design** (GitHub Copilot)
**Prompt**: *"I am creating a API controller with format - Controller-> API->Rest . I want to add version like v1 and extendable to GraphQL Later on. Can you suggest directory structure for Symfony 7.4. For now we need to create for REST API."*

### 2. **Database Schema Planning** (ChatGPT)
**Prompt**: *"I have already done the Project Setup with the required directory structure. The API is working with echo and exit, I have tested. My next step is to see how I can handle Account and User relation. Because I need to use JWT Authentication next. So, I need to create the API for Registering the user and Login API to get the JWT Token? I want to stored "account_number" as encrypted. So that in database also its safe, only we can use application check it."*

### 3. **Entity and Migration Creation** (GitHub Copilot)
**Prompt**: *"Let's create Entity, Migrations and Tables one by one. We will start for the below table structures:"*

### 4. **JWT Authentication Setup** (GitHub Copilot)
**Prompt**: *"Now, I need to install and configure the JWT for my API with "composer require lexik/jwt-authentication-bundle" and generate key with "bin/console lexik:jwt:generate-keypair""*

### 5. **Database Security Enhancement** (Self-Implemented)
**Implementation**: *"My database information is visible in .env file. Let's encrypt that first: bin/console secrets:generate-keys, bin/console secrets:set DATABASE_URL and removed it from the .env file."*

### 6. **Account Management & Encryption** (GitHub Copilot)
**Prompt**: *"I have created CurrencyRateFixtures for seeding the Currency Data. Now, I have the accounts which are related to users table with user_id column. Now I have to create the APIs to create the: 1A. Accounts - Based on User id. Accounts table have "account_number_enc" and "account_number_hash" column. We can use sodium_crypto_secretbox() for encryption. 1B. My Controller should be only for request and response handling, other things like DTO mapping shouldn't it be moved to service or provider. What you think? 2. Transfer APIs - In which we will allow user to transfer from one Account to Another"*

### 7. **Transaction System Refactoring** (GitHub Copilot)
**Prompt**: *"Let's refactor the "transfer" method and make it slim to handle only the request and response. Also, we need to use the Transaction - Money Transfer, Account settlement, transactions and LedgerEntry. Each task need to be done in methods we should get appropriate response."*

### 8. **Redis Caching Implementation** (GitHub Copilot)
**Prompt**: *"My Transaction task is completed. Now I want to introduce the Redis for the caching work. Let's say - Currency Conversion that I can cache and implement the "Cache-aside" strategy. Also, I want make it more flexible - suppose the Redis is not available then we can PHP Symfony Cache with "composer require symfony/cache"."*

### 9. **PHPUnit Testing Suite** (GitHub Copilot)
**Prompt**: *"Let's add the PHPUnit Testing to our application by running "composer require --dev symfony/test-pack" and creating the Unit Test, Integration Test and Functional Test for our APIs."*

### 10. **Multilingual Support** (GitHub Copilot)
**Prompt**: *"We should show the message in translated languages. Let's install the translator with "composer require symfony/translation" and update our application message to use the translated information."*

### 11. **Documentation & README** (GitHub Copilot)
**Prompt**: *"Finally let's add a README.md file with installation steps, prerequisites (PHP 8.3, Symfony 7.4, MySQL 8, Redis Server), git clone instructions, cryptographic keys setup, JWT key pair generation, and development time tracking."*

### **AI Tools Summary**
- **GitHub Copilot**: Primary development assistant for code generation, architecture decisions, and implementation guidance
- **ChatGPT**: Database schema planning and architectural consultations
- **Development Approach**: Iterative development with AI-assisted problem-solving and best practices implementation

## License

This project is licensed under the MIT License.

## Support

For issues and questions:
- Create an issue on GitHub
- Check the test suite for examples
- Review the API documentation

---

**MoneyMove API** - Secure, multilingual financial transactions made simple. 🚀