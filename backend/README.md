# Vexa Backend

The backend of the Vexa application is built with Laravel 13, providing a RESTful API for the frontend to interact with. It handles user authentication, conversation management, message storage, and AI integration.

## Tech Stack

- **Framework**: Laravel 13
- **Language**: PHP 8.3
- **Database**: MySQL for application persistence, SQLite in-memory for automated tests
- **Authentication**: Laravel Sanctum
- **AI Integration**: Groq API

## Key Components

### Authentication

The authentication system uses Laravel Sanctum for API token authentication. Users can register and login through the `/api/register` and `/api/login` endpoints respectively.

### Conversation Management

Conversations are stored in the database with a relationship to users. Each conversation can have multiple messages associated with it.

### Message System

Messages are stored with their role (user or assistant) and content. They maintain the context of the conversation for AI interactions.

### AI Integration

The application integrates with the Groq API to provide AI-powered responses. The `GroqAiProvider` class handles communication with the API, including error handling for various scenarios like rate limiting, authentication failures, and connection issues.

## Database Structure

### Users Table

- `id` - Primary key
- `name` - User's name
- `email` - Unique email address
- `password` - Hashed password
- `timestamps` - Created/updated timestamps

### Conversations Table

- `id` - Primary key
- `user_id` - Foreign key to users table
- `title` - Optional conversation title
- `timestamps` - Created/updated timestamps

### Messages Table

- `id` - Primary key
- `conversation_id` - Foreign key to conversations table
- `role` - Message role (user/assistant)
- `content` - Message content
- `timestamps` - Created/updated timestamps

## API Endpoints

| Method | Endpoint                        | Middleware | Description                    |
|--------|---------------------------------|------------|--------------------------------|
| POST   | `/api/register`                 | guest      | User registration              |
| POST   | `/api/login`                    | guest      | User login                     |
| POST   | `/api/logout`                   | auth:sanctum | User logout                   |
| GET    | `/api/user`                     | auth:sanctum | Get authenticated user        |
| GET    | `/api/conversations`            | auth:sanctum | List user's conversations     |
| POST   | `/api/conversations`            | auth:sanctum | Create new conversation       |
| GET    | `/api/conversations/{id}`       | auth:sanctum | Get conversation details      |
| PUT    | `/api/conversations/{id}`       | auth:sanctum | Update conversation           |
| DELETE | `/api/conversations/{id}`       | auth:sanctum | Delete conversation           |
| GET    | `/api/conversations/{id}/messages` | auth:sanctum | List conversation messages  |
| POST   | `/api/conversations/{id}/messages` | auth:sanctum | Create new message          |
| POST   | `/api/conversations/{id}/ai-reply` | auth:sanctum | Generate AI response         |

## Configuration

The backend requires several environment variables to be set in the `.env` file:

```env
APP_NAME=Vexa
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vexa
DB_USERNAME=root
DB_PASSWORD=

AI_PROVIDER=groq
GROQ_API_KEY=your_groq_api_key_here
GROQ_MODEL=llama-3.1-8b-instant
GROQ_BASE_URL=https://api.groq.com/openai/v1

AI_TIMEOUT=30
AI_CONNECT_TIMEOUT=10
AI_MAX_TOKENS=1024
AI_TEMPERATURE=0.7
```

## Development

To run the backend in development mode:

```bash
php artisan serve
```

This will start the development server at http://localhost:8000.

## Testing

The application uses Pest for testing. To run tests:

```bash
php artisan test
```

## Folder Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AuthController.php
│   │       ├── ConversationController.php
│   │       ├── MessageController.php
│   │       └── AiReplyController.php
│   └── Resources/
├── Models/
│   ├── User.php
│   ├── Conversation.php
│   └── Message.php
├── Services/
│   └── AI/
│       ├── GroqAiProvider.php
│       └── AiService.php
└── Exceptions/
    └── AI/
```