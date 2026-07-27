import { Component, OnInit, signal } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService, User } from '../../services/auth.service';
import { Conversation, ConversationService } from '../../services/conversation.service';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [],
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

  constructor(
    private authService: AuthService,
    private conversationService: ConversationService,
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
    // Close mobile sidebar after selection
    this.closeSidebar();
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