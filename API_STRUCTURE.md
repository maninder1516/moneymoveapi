# MoneyMove API - Directory Structure

This API follows a clean, versioned architecture that supports both REST and GraphQL endpoints.

## Directory Structure

```
src/
├── Controller/Api/
│   ├── BaseApiController.php          # Base controller with common API functionality
│   ├── V1/
│   │   ├── BaseV1Controller.php       # V1 base controller with version-specific features
│   │   ├── Rest/                      # REST endpoints for V1
│   │   │   └── UserController.php     # Example user REST controller
│   │   └── GraphQL/                   # GraphQL endpoints for V1
│   │       └── GraphQLController.php  # GraphQL controller (placeholder)
│   └── V2/
│       ├── BaseV2Controller.php       # V2 base controller
│       ├── Rest/                      # REST endpoints for V2
│       └── GraphQL/                   # GraphQL endpoints for V2
├── Service/Api/
│   ├── V1/
│   │   └── UserService.php           # Business logic for V1
│   └── V2/                           # Services for V2
├── Dto/Api/
│   ├── V1/
│   │   ├── UserDto.php               # User data transfer object
│   │   └── CreateUserRequestDto.php  # Request DTO for creating users
│   └── V2/                           # DTOs for V2
├── Exception/Api/
│   └── UserNotFoundException.php      # API-specific exceptions
├── EventListener/Api/
│   └── ApiExceptionListener.php       # Global exception handler for API
├── Serializer/Api/V1/                 # Custom serializers (if needed)
└── Validator/Api/V1/                  # Custom validators (if needed)
```

## Key Features

### 1. **Versioned API Structure**
- Clear separation between API versions (V1, V2, etc.)
- Version-specific base controllers
- Independent evolution of API versions

### 2. **REST and GraphQL Support**
- Separate directories for REST and GraphQL endpoints
- Extensible structure for future GraphQL implementation
- Shared base functionality between different API types

### 3. **Modern PHP 8.3 Features**
- Constructor property promotion
- Readonly classes for DTOs
- Nullable types and union types
- Attributes for routing and validation

### 4. **Clean Architecture**
- Controllers handle HTTP concerns only
- Services contain business logic
- DTOs for data transfer and validation
- Exception handlers for consistent error responses

## API Endpoints

### V1 REST Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | `/api/v1/users` | List all users (paginated) |
| GET    | `/api/v1/users/{id}` | Get user by ID |
| POST   | `/api/v1/users` | Create new user |
| PUT    | `/api/v1/users/{id}` | Update user |
| DELETE | `/api/v1/users/{id}` | Delete user |

### Future GraphQL Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST   | `/api/v1/graphql` | GraphQL endpoint |
| GET    | `/api/v1/graphql/schema` | GraphQL schema |

## Response Format

All API responses follow a consistent format:

```json
{
  "success": true,
  "message": "Success message",
  "data": { ... },
  "pagination": {  // Only for paginated responses
    "total": 100,
    "page": 1,
    "limit": 10,
    "pages": 10
  }
}
```

Error responses:

```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... },
  "timestamp": "2025-12-04 10:30:00"
}
```

## Adding New API Versions

To add a new API version (e.g., V3):

1. Create directory structure:
   ```
   src/Controller/Api/V3/
   src/Service/Api/V3/
   src/Dto/Api/V3/
   ```

2. Create base controller:
   ```php
   namespace App\Controller\Api\V3;
   
   #[Route('/api/v3', name: 'api_v3_')]
   abstract class BaseV3Controller extends BaseApiController
   ```

3. Update configuration in `config/packages/api.yaml`

## Next Steps

1. **Install Additional Packages:**
   ```bash
   composer require symfony/validator
   composer require symfony/serializer
   composer require doctrine/orm
   ```

2. **Add Authentication:**
   ```bash
   composer require lexik/jwt-authentication-bundle
   ```

3. **Add GraphQL Support:**
   ```bash
   composer require overblog/graphql-bundle
   ```

4. **Add API Documentation:**
   ```bash
   composer require nelmio/api-doc-bundle
   ```

This structure provides a solid foundation for a scalable, maintainable API that can grow with your application needs.