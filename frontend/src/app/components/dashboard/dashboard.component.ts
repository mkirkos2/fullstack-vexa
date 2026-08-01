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

  // AI reply state
  aiReplyLoading = signal(false);
  aiReplyError = signal('');

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
        this.loadConversations();
      },
      error: (error) => {
        this.isLoading.set(false);
        if (error.status === 401) {
          this.router.navigate(['/login']);
        } else {
          this.isError.set(true);
          this.errorMessage.set('Failed to load user data. Please try again.');
        }
      }
    });
  }

  loadConversations(): void {
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
      }
    });
  }

  selectConversation(conversation: Conversation): void {
    this.selectedConversation.set(conversation);
    this.messages.set([]);
    this.messagesError.set(false);
    this.aiReplyError.set('');
    this.loadMessages();
    this.closeSidebar();
  }

  loadMessages(): void {
    const conversation = this.selectedConversation();
    if (!conversation) {
      return;
    }

    if (this.messagesLoading()) {
      return;
    }

    this.messagesLoading.set(true);
    this.messagesError.set(false);

    this.messageService.getMessages(conversation.id).subscribe({
      next: (response) => {
        if (this.selectedConversation()?.id === conversation.id) {
          this.messages.set(response.data);
          this.messagesLoading.set(false);
        }
      },
      error: (error) => {
        if (this.selectedConversation()?.id === conversation.id) {
          this.messagesLoading.set(false);
          if (error.status === 401) {
            this.router.navigate(['/login'], { replaceUrl: true });
          } else if (error.status === 404) {
            this.removeConversation(conversation.id);
            this.selectedConversation.set(null);
            this.messages.set([]);
          } else {
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
    if (!(event instanceof KeyboardEvent)) {
      return;
    }

    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.sendMessage();
    }
  }

  sendMessage(): void {
    if (!this.selectedConversation() || this.sendingMessage() || this.aiReplyLoading()) {
      return;
    }

    const content = this.messageContent().trim();
    if (!content) {
      return;
    }

    if (content.length > 50000) {
      return;
    }

    const conversationId = this.selectedConversation()!.id;
    this.sendingMessage.set(true);
    this.aiReplyError.set('');

    this.messageService.createMessage(conversationId, content).subscribe({
      next: (response) => {
        const newMessage = response.data;
        this.messages.set([...this.messages(), newMessage]);
        this.messageContent.set('');
        this.moveConversationToTop(conversationId);
        this.sendingMessage.set(false);

        this.generateAiReply(conversationId);
      },
      error: (error) => {
        this.sendingMessage.set(false);
        if (error.status === 401) {
          this.router.navigate(['/login'], { replaceUrl: true });
        } else if (error.status === 404) {
          this.removeConversation(conversationId);
          this.selectedConversation.set(null);
          this.messages.set([]);
        }
      }
    });
  }

  generateAiReply(conversationId: number): void {
    if (!this.selectedConversation() || this.aiReplyLoading()) {
      return;
    }

    if (this.selectedConversation()?.id !== conversationId) {
      return;
    }

    this.aiReplyLoading.set(true);
    this.aiReplyError.set('');

    this.messageService.generateAiReply(conversationId).subscribe({
      next: (response) => {
        if (this.selectedConversation()?.id === conversationId) {
          const aiMessage = response.data;
          this.messages.set([...this.messages(), aiMessage]);
          this.aiReplyLoading.set(false);
        }
      },
      error: (error) => {
        if (this.selectedConversation()?.id === conversationId) {
          this.aiReplyLoading.set(false);
          if (error.status === 401) {
            this.router.navigate(['/login'], { replaceUrl: true });
          } else if (error.status === 404) {
            this.removeConversation(conversationId);
            this.selectedConversation.set(null);
            this.messages.set([]);
          } else if (error.status === 409) {
            this.aiReplyError.set('The conversation changed while Vexa was replying. Please try again.');
          } else if (error.status === 422) {
            this.aiReplyError.set(error.error?.message || 'Vexa cannot reply to this conversation yet.');
          } else if (error.status === 429) {
            this.aiReplyError.set('Vexa is receiving too many requests. Please try again shortly.');
          } else if (error.status === 502) {
            this.aiReplyError.set('Vexa could not generate a response. Please try again.');
          } else if (error.status === 503) {
            this.aiReplyError.set('Vexa is temporarily unavailable. Please try again.');
          } else {
            this.aiReplyError.set('Unable to get a response from Vexa. Please try again.');
          }
        }
      }
    });
  }

  moveConversationToTop(conversationId: number): void {
    const conversations = this.conversations();
    const conversationIndex = conversations.findIndex(conv => conv.id === conversationId);

    if (conversationIndex !== -1) {
      const conversation = conversations[conversationIndex];
      const updatedConversations = [
        conversation,
        ...conversations.slice(0, conversationIndex),
        ...conversations.slice(conversationIndex + 1)
      ];
      this.conversations.set(updatedConversations);
    }
  }

  createNewConversation(): void {
    if (this.creatingConversation()) {
      return;
    }

    this.creatingConversation.set(true);

    this.conversationService.createConversation(null).subscribe({
      next: (response) => {
        const newConversation = response.data;
        this.conversations.set([newConversation, ...this.conversations()]);
        this.selectConversation(newConversation);
        this.conversationsError.set(false);
        this.creatingConversation.set(false);
      },
      error: (error) => {
        this.creatingConversation.set(false);
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
        this.router.navigate(['/login'], { replaceUrl: true });
      },
      error: () => {
        this.isLoggingOut.set(false);
        this.errorMessage.set('Unable to sign out. Please try again.');
      }
    });
  }
}