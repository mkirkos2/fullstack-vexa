# Vexa Frontend

The frontend of the Vexa application is built with Angular 22, providing a responsive and intuitive user interface for interacting with the chat functionality.

## Tech Stack

- **Framework**: Angular 22
- **Language**: TypeScript
- **Styling**: Tailwind CSS
- **Build Tool**: Angular CLI
- **Testing**: Jasmine & Karma

## Key Features

### User Authentication

- Registration and login forms
- Token-based authentication
- Protected routes
- User session management

### Conversation Interface

- Sidebar for conversation navigation
- Real-time messaging display
- Message input with send functionality
- AI response loading states

### Responsive Design

- Mobile-friendly layout
- Collapsible sidebar for smaller screens
- Adaptive message bubbles

## Components

### Authentication Components

- `login` - User login form
- `register` - User registration form

### Main Components

- `dashboard` - Main application interface
  - Header with logout functionality
  - Sidebar for conversation management
  - Main chat area with messages
  - Message input area

## Services

### Auth Service

Handles user authentication including registration, login, logout, and current user retrieval.

### Conversation Service

Manages conversation CRUD operations:
- Get all user conversations
- Create new conversation
- Update conversation
- Delete conversation

### Message Service

Handles message operations and AI integration:
- Get messages for a conversation
- Create new message
- Generate AI reply

## State Management

The application uses Angular signals for state management:
- User authentication state
- Conversation loading states
- Message loading states
- UI interaction states (sidebar open/close, loading indicators)

## Styling

The application uses Tailwind CSS for styling with a clean, modern design:
- Dark mode friendly color scheme
- Responsive breakpoints
- Consistent spacing and typography
- Interactive element states (hover, focus)

## Development

To run the frontend in development mode:

```bash
npm start
```

This will start the development server at http://localhost:4200.

## Building

To build the application for production:

```bash
npm run build
```

## Testing

To run tests:

```bash
npm test
```

## Folder Structure

```
src/
├── app/
│   ├── components/
│   │   ├── dashboard/
│   │   ├── login/
│   │   └── register/
│   ├── services/
│   │   ├── auth.service.ts
│   │   ├── conversation.service.ts
│   │   └── message.service.ts
│   ├── app.config.ts
│   ├── app.routes.ts
│   └── app.ts
├── assets/
└── styles/
```

## API Integration

The frontend communicates with the backend API through Angular services that use HttpClient. During development, API requests are proxied to the backend server at `http://localhost:8000` using the proxy configuration.

## Environment Configuration

The application uses Angular's environment files to manage different configurations:

```typescript
// environments/environment.ts
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api'
};
```