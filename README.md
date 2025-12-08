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
git clone -b moneymoveapi https://github.com/maninder1516/moneymoveapi.git moneymoveapi
```

### 2. Copy Environment File

```bash
cd moneymoveapi && cp .env.dev .env
```

### 3. Install Dependencies

```bash
composer install
```

### 4. Generate Cryptographic Keys & Configure Secure Secrets

**🔒 Security Best Practice**: All sensitive configuration is encrypted using Symfony's secrets management.

Generate encryption keys for secure configuration storage:

```bash
bin/console secrets:generate-keys
```

### 5. Configure Database Connection

Set your MySQL database connection string:

```bash
bin/console secrets:set DATABASE_URL
```

When prompted, enter your database URL in this format:
```
mysql://username:password@127.0.0.1:3306/moneymove_db?serverVersion=8.0&charset=utf8mb4
```

**📝 Note**: If your username, password, host, or database name contain special URI characters (`: / ? # [ ] @ ! $ & ' ( ) * + , ; =`), you must URL encode them. For example:
- `@` symbol becomes `%40`
- `#` symbol becomes `%23`
- `&` symbol becomes `%26`

Example with encoded password containing `@`:
```
mysql://user:my%40password@127.0.0.1:3306/moneymove_db?serverVersion=8.0&charset=utf8mb4
```

**For Test Environment** (recommended for comprehensive testing):
```bash
bin/console secrets:set DATABASE_URL --env=test
```
Enter your test database URL:
```
mysql://username:password@127.0.0.1:3306/moneymove_test_db?serverVersion=8.0&charset=utf8mb4
```

### 6. Configure Redis Connection

Set your Redis server connection:

```bash
bin/console secrets:set REDIS_URL
```

When prompted, enter your Redis URL (typically):
```
redis://localhost:6379
```

**For Test Environment** (recommended for isolated testing):
```bash
bin/console secrets:set REDIS_URL --env=test
```
Enter your test Redis URL (can be same or different instance):
```
redis://localhost:6379/1
```

**⚠️ Important**: After setting up your DATABASE_URL and REDIS_URL secrets, comment out the temporary entries in **ALL environment files** (`.env`, `.env.dev`, `.env.local`, etc.) to ensure secrets take precedence:
```bash
# Comment out these lines in ALL .env* files after secrets are configured:
# DATABASE_URL="mysql://user:pass@127.0.0.1:3306/moneymove?serverVersion=8.0.32&charset=utf8mb4"
# REDIS_URL="redis://127.0.0.1:6379"
```
**Note**: Symfony loads environment variables in order of precedence. If DATABASE_URL or REDIS_URL exist in any `.env*` file, they will override your encrypted secrets. Make sure to comment them out in all environment files.

### 7. Generate JWT Key Pair

Create the cryptographic keys for JWT token authentication:

```bash
bin/console lexik:jwt:generate-keypair
```

**Set JWT Directory Permissions**: Ensure the JWT directory is writable:

```bash
chmod -R 775 config/jwt
```

### 8. Create Database Schema

Run the database migrations to create the required tables:

```bash
bin/console doctrine:database:create
bin/console doctrine:migrations:migrate
```

### 9. Test Database Setup (Recommended)

Create and configure the test database for comprehensive testing:

```bash
bin/console doctrine:database:create --env=test
bin/console doctrine:migrations:migrate --env=test
```

### 10. Load Sample Data (Optional)

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

### 1. User Registration
**Endpoint**: `POST /api/v1/auth/register` (Route: `api_v1_auth_register`)

**Request Example**:
```http
POST http://moneymoveapi.com/api/v1/auth/register
Content-Type: application/json

{
    "name": "Maninder Kumar",
    "email": "maninder1516@gmail.com",
    "username": "maninder",
    "password": "#Maninder@123"
}
```

**Response (201 Created)**:
```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 1,
            "name": "Maninder Kumar",
            "email": "maninder1516@gmail.com",
            "username": "maninder",
            "created_at": "2025-12-08 10:30:00"
        }
    }
}
```

### 2. User Authentication (Login)
**Endpoint**: `POST /api/v1/auth/login` (Route: `api_login_check`)

**Request Example 1**:
```http
POST http://moneymoveapi.com/api/v1/auth/login
Content-Type: application/json

{
    "username": "maninder@example.com",
    "password": "#Maninder@123"
}
```

**Request Example 2**:
```http
POST http://moneymoveapi.com/api/v1/auth/login
Content-Type: application/json

{
    "username": "john@example.com",
    "password": "password123"
}
```

**Response (200 OK)**:
```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3NjUxODE5NTQsImV4cCI6MTc2NTE4NTU1NCwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoibWFuaW5kZXJAZXhhbXBsZS5jb20ifQ..."
}
```

### 3. Get All Users
**Endpoint**: `GET /api/v1/users`

**Request Example**:
```http
GET http://moneymoveapi.com/api/v1/users
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

### 4. List User's Accounts
**Endpoint**: `GET /api/v1/accounts` (Route: `api_v1_accounts_list`)

**Request Example**:
```http
GET http://moneymoveapi.com/api/v1/accounts
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

**Response (200 OK)**:
```json
{
    "success": true,
    "message": "Accounts retrieved successfully",
    "data": {
        "accounts": [
            {
                "id": 1,
                "account_number": "ACC00011825916424",
                "currency": "USD",
                "account_type": "checking",
                "balance": "1500.00",
                "created_at": "2025-12-08T10:30:00Z"
            }
        ]
    }
}
```

### 5. Get Single Account
**Endpoint**: `GET /api/v1/accounts/{id}` (Route: `api_v1_accounts_show`)

**Request Example**:
```http
GET http://moneymoveapi.com/api/v1/accounts/1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

### 6. Create New Account
**Endpoint**: `POST /api/v1/accounts` (Route: `api_v1_accounts_create`)

**Request Example**:
```http
POST http://moneymoveapi.com/api/v1/accounts
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
Content-Type: application/json

{
    "currency": "GBP",
    "accountType": "savings",
    "initialBalance": "1000.00"
}
```

**Response (201 Created)**:
```json
{
    "success": true,
    "message": "Account created successfully",
    "data": {
        "account": {
            "id": 2,
            "account_number": "ACC00021825915635",
            "currency": "GBP",
            "account_type": "savings",
            "balance": "1000.00",
            "created_at": "2025-12-08T11:00:00Z"
        }
    }
}
```

### 7. Delete Account
**Endpoint**: `DELETE /api/v1/accounts/{id}` (Route: `api_v1_accounts_delete`)

**Request Example**:
```http
DELETE http://moneymoveapi.com/api/v1/accounts/2
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

### 8. Transfer Money
**Endpoint**: `POST /api/v1/accounts/{fromId}/transfer` (Route: `api_v1_accounts_transfer`)

#### 8A. Same-Currency Transfer
**Request Example**:
```http
POST http://moneymoveapi.com/api/v1/accounts/1/transfer
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
Content-Type: application/json

{
    "to_account_number": "ACC00011825916424",
    "amount": "50.00",
    "note": "Monthly savings transfer"
}
```

#### 8B. Cross-Currency Transfer (with conversion)
**Request Example**:
```http
POST http://moneymoveapi.com/api/v1/accounts/2/transfer
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
Content-Type: application/json

{
    "to_account_number": "ACC00021825915635",
    "amount": "100.00",
    "note": "International transfer"
}
```

**Transfer Response (200 OK)**:
```json
{
    "success": true,
    "message": "Transfer completed successfully",
    "data": {
        "transaction": {
            "id": "TXN123456789",
            "from_account": "ACC00011825916424",
            "to_account": "ACC00021825915635",
            "amount": "100.00",
            "currency_from": "USD",
            "currency_to": "GBP",
            "conversion_rate": "0.79",
            "converted_amount": "79.00",
            "fee": "2.50",
            "note": "International transfer",
            "status": "completed",
            "created_at": "2025-12-08T12:00:00Z"
        }
    }
}
```

### 9. List Transactions
**Endpoint**: `GET /api/v1/transactions`

**Request Example**:
```http
GET http://moneymoveapi.com/api/v1/transactions
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

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

- **🔐 Cryptographic Secrets Management** - All sensitive configuration (DATABASE_URL, REDIS_URL) encrypted using Symfony secrets vault for both production and test environments
- **JWT Token Authentication** with RS256 encryption
- **Database Field Encryption** for sensitive data (account numbers, personal information)
- **Redis Session Management** with secure caching and isolated test environment
- **Input Validation** with comprehensive sanitization
- **CORS Protection** with configurable origins
- **Environment Isolation** - Separate encrypted configurations for development, test, and production

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

## Future Scope

### 🔒 **Security Enhancements**

#### IP-Based Access Control
- **Feature**: Block API access based on IP addresses
- **Implementation**: IP whitelist/blacklist functionality for enhanced security
- **Benefits**: Prevent unauthorized access from specific geographical regions or known malicious IPs
- **Configuration**: Admin dashboard for managing IP rules and exceptions

### 🔢 **Account Number Generation**

#### Sequential Account Numbers
- **Current**: Account numbers generated using random numbers (e.g., `ACC00011825916424`)
- **Future**: Implement incremental sequential account number generation
- **Benefits**: 
  - Better tracking and auditing capabilities
  - Easier account number validation
  - Compliance with banking industry standards
- **Implementation**: Database sequence-based generation with configurable prefixes

### ⚡ **Rate Limiting Configuration**

#### Dynamic Rate Limiting
- **Current**: Rate limiting configured in YAML files (static configuration)
- **Future**: Make rate limiting configurable through API/Admin interface
- **Features**:
  - Per-user rate limiting
  - Per-endpoint rate limiting
  - Dynamic adjustment based on user tier/subscription
  - Real-time rate limit monitoring and alerts
- **Configuration Options**:
  - Requests per minute/hour/day
  - Burst allowance
  - Penalty timeouts
  - Whitelist for premium users

### 📊 **Additional Planned Features**

#### Advanced Analytics
- Transaction pattern analysis
- Fraud detection algorithms
- Real-time spending insights
- Currency exchange rate predictions

#### Enhanced Multilingual Support
- Right-to-left language support (Arabic, Hebrew)
- Additional languages (German, Italian, Portuguese, Chinese)
- Localized currency formatting
- Cultural date/time formatting

#### GraphQL API Complete Implementation
- Full GraphQL schema implementation
- Real-time subscriptions for account updates
- Advanced querying capabilities
- GraphQL playground integration

#### Mobile SDK
- Native iOS and Android SDKs
- React Native wrapper
- Flutter integration
- Biometric authentication support

## License

This project is licensed under the MIT License.

## Support

For issues and questions:
- Create an issue on GitHub
- Check the test suite for examples
- Review the API documentation

---

**MoneyMove API** - Secure, multilingual financial transactions made simple. 🚀