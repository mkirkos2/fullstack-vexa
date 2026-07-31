import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService, User } from '../../services/auth.service';
import { Conversation, ConversationService } from '../../services/conversation.service';
import { Message, MessageService } from '../../services/message.service';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [FormsModule],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.css'
})
export class DashboardComponent implements OnInit {
  currentUser = signal<User | null>(null);
  isLoading = signal(true);
  isError = signal(false);
  errorMessage = signal('');
  isLoggingOut = signal(false);
  isSidebarOpen = signal(false);

  // Conversation state
  conversations = signal<Conversation[]>([]);
  selectedConversation = signal<Conversation | null>(null);
  conversationsLoading = signal(false);
  conversationsError = signal(false);
  creatingConversation = signal(false);

  // Message state
  messages = signal<Message[]>([]);
  messagesLoading = signal(false);
  messagesError = signal(false);
  sendingMessage = signal(false);
  messageContent = signal('');

  constructor(
    private authService: AuthService,
    private conversationService: ConversationService,
    private messageService: MessageService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.loadCurrentUser();
  }

  loadCurrentUser(): void {
    this.isLoading.set(true);
    this.isError.set(false);
    this.errorMessage.set('');

    this.authService.getCurrentUser().subscribe({
      next: (response) => {
        this.currentUser.set(response.data.user);
        this.isLoading.set(false);
        // Load conversations after user is loaded
        this.loadConversations();
      },
      error: (error) => {
        this.isLoading.set(false);
        if (error.status === 401) {
          // Unauthorized - redirect to login
          this.router.navigate(['/login']);
        } else {
          // Other error
          this.isError.set(true);
          this.errorMessage.set('Failed to load user data. Please try again.');
        }
      }
    });
  }

  loadConversations(): void {
    // Prevent duplicate loading requests
    if (this.conversationsLoading()) {
      return;
    }

    this.conversationsLoading.set(true);
    this.conversationsError.set(false);

    this.conversationService.getConversations().subscribe({
      next: (response) => {
        this.conversations.set(response.data);
        this.conversationsLoading.set(false);
      },
      error: (error) => {
        this.conversationsLoading.set(false);
        this.conversationsError.set(true);
        // Don't redirect to login for conversation loading errors
        console.error('Failed to load conversations:', error);
      }
    });
  }

  selectConversation(conversation: Conversation): void {
    this.selectedConversation.set(conversation);
    // Clear messages from previously selected conversation
    this.messages.set([]);
    // Clear previous message errors
    this.messagesError.set(false);
    // Load messages for the selected conversation
    this.loadMessages();
    // Close mobile sidebar after selection
    this.closeSidebar();
  }

  loadMessages(): void {
    const conversation = this.selectedConversation();
    if (!conversation) {
      return;
    }

    // Prevent duplicate loading requests
    if (this.messagesLoading()) {
      return;
    }

    this.messagesLoading.set(true);
    this.messagesError.set(false);

    this.messageService.getMessages(conversation.id).subscribe({
      next: (response) => {
        // Ensure we're still on the same conversation
        if (this.selectedConversation()?.id === conversation.id) {
          this.messages.set(response.data);
          this.messagesLoading.set(false);
        }
      },
      error: (error) => {
        // Ensure we're still on the same conversation
        if (this.selectedConversation()?.id === conversation.id) {
          this.messagesLoading.set(false);
          if (error.status === 401) {
            // Unauthorized - redirect to login
            this.router.navigate(['/login'], { replaceUrl: true });
          } else if (error.status === 404) {
            // Conversation not found - remove from list and clear selection
            this.removeConversation(conversation.id);
            this.selectedConversation.set(null);
            this.messages.set([]);
          } else {
            // Other error
            this.messagesError.set(true);
          }
        }
      }
    });
  }

  removeConversation(conversationId: number): void {
    this.conversations.set(this.conversations().filter(conv => conv.id !== conversationId));
  }

  handleKeyDown(event: Event): void {
    // Type guard to ensure we have a KeyboardEvent
    if (!(event instanceof KeyboardEvent)) {
      return;
    }

    // Allow Shift+Enter to create a new line
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.sendMessage();
    }
  }

  sendMessage(): void {
    // Prevent submission if no conversation is selected or already sending
    if (!this.selectedConversation() || this.sendingMessage()) {
      return;
    }

    const content = this.messageContent().trim();
    // Prevent submission if content is empty or whitespace-only
    if (!content) {
      return;
    }

    // Prevent submission if content is too long
    if (content.length > 50000) {
      return;
    }

    const conversationId = this.selectedConversation()!.id;
    this.sendingMessage.set(true);

    this.messageService.createMessage(conversationId, content).subscribe({
      next: (response) => {
        const newMessage = response.data;
        // Append the new message
        this.messages.set([...this.messages(), newMessage]);
        // Clear the message content
        this.messageContent.set('');
        // Move the selected conversation to the top of the sidebar
        this.moveConversationToTop(conversationId);
        // Reset sending state
        this.sendingMessage.set(false);
      },
      error: (error) => {
        this.sendingMessage.set(false);
        if (error.status === 401) {
          // Unauthorized - redirect to login
          this.router.navigate(['/login'], { replaceUrl: true });
        } else if (error.status === 404) {
          // Conversation not found - remove from list and clear selection
          this.removeConversation(conversationId);
          this.selectedConversation.set(null);
          this.messages.set([]);
        } else {
          // Other error - show message to user
          console.error('Failed to send message:', error);
        }
      }
    });
  }

  moveConversationToTop(conversationId: number): void {
    const conversations = this.conversations();
    const conversationIndex = conversations.findIndex(conv => conv.id === conversationId);

    if (conversationIndex !== -1) {
      const conversation = conversations[conversationIndex];
      // Create a new array with the conversation moved to the top
      const updatedConversations = [
        conversation,
        ...conversations.slice(0, conversationIndex),
        ...conversations.slice(conversationIndex + 1)
      ];
      this.conversations.set(updatedConversations);
    }
  }

  createNewConversation(): void {
    // Prevent duplicate requests
    if (this.creatingConversation()) {
      return;
    }

    this.creatingConversation.set(true);

    this.conversationService.createConversation(null).subscribe({
      next: (response) => {
        const newConversation = response.data;
        // Insert the new conversation at the top of the sidebar
        this.conversations.set([newConversation, ...this.conversations()]);
        // Select the new conversation
        this.selectConversation(newConversation);
        // Clear any previous conversation error
        this.conversationsError.set(false);
        // Reset creating state
        this.creatingConversation.set(false);
      },
      error: (error) => {
        this.creatingConversation.set(false);
        // Show error message
        console.error('Failed to create conversation:', error);
      }
    });
  }

  toggleSidebar(): void {
    this.isSidebarOpen.set(!this.isSidebarOpen());
  }

  closeSidebar(): void {
    this.isSidebarOpen.set(false);
  }

  logout(): void {
    if (this.isLoggingOut()) {
      return;
    }

    this.isLoggingOut.set(true);
    this.errorMessage.set('');

    this.authService.logout().subscribe({
      next: () => {
        this.isLoggingOut.set(false);
        // Navigate to login with replaceUrl to prevent back button from returning to dashboard
        this.router.navigate(['/login'], { replaceUrl: true });
      },
      error: () => {
        this.isLoggingOut.set(false);
        this.errorMessage.set('Unable to sign out. Please try again.');
      }
    });
  }
}