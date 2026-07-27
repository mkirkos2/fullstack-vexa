import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DashboardComponent } from './dashboard.component';
import { AuthService } from '../../services/auth.service';
import { ConversationService } from '../../services/conversation.service';
import { Router } from '@angular/router';
import { of, throwError, Observable } from 'rxjs';
import { Conversation } from '../../services/conversation.service';

describe('DashboardComponent', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;
  let mockAuthService: any;
  let mockConversationService: any;
  let mockRouter: any;

  beforeEach(async () => {
    // Create mock services with spy-like objects
    mockAuthService = {
      getCurrentUser: vi.fn().mockReturnValue(of({
        data: {
          user: {
            id: 1,
            name: 'Test User',
            email: 'test@example.com',
            email_verified_at: null,
            created_at: '2023-01-01',
            updated_at: '2023-01-01'
          }
        }
      })),
      logout: vi.fn().mockReturnValue(of({}))
    };

    mockConversationService = {
      getConversations: vi.fn().mockReturnValue(of({ data: [] })),
      createConversation: vi.fn().mockReturnValue(of({
        data: {
          id: 1,
          title: null,
          created_at: '2023-01-01',
          updated_at: '2023-01-01'
        }
      }))
    };

    mockRouter = {
      navigate: vi.fn()
    };

    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        { provide: AuthService, useValue: mockAuthService },
        { provide: ConversationService, useValue: mockConversationService },
        { provide: Router, useValue: mockRouter }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should toggle sidebar', () => {
    expect(component.isSidebarOpen()).toBeFalsy();

    component.toggleSidebar();
    expect(component.isSidebarOpen()).toBeTruthy();

    component.toggleSidebar();
    expect(component.isSidebarOpen()).toBeFalsy();
  });

  it('should close sidebar', () => {
    component.isSidebarOpen.set(true);
    component.closeSidebar();
    expect(component.isSidebarOpen()).toBeFalsy();
  });

  it('should load current user successfully', () => {
    // Reset spies
    mockAuthService.getCurrentUser.mockClear();
    mockConversationService.getConversations.mockClear();

    const mockUserResponse = {
      data: {
        user: {
          id: 1,
          name: 'Test User',
          email: 'test@example.com',
          email_verified_at: null,
          created_at: '2023-01-01',
          updated_at: '2023-01-01'
        }
      }
    };

    const mockConversationsResponse = {
      data: [
        {
          id: 1,
          title: 'Test Conversation',
          created_at: '2023-01-01',
          updated_at: '2023-01-01'
        }
      ]
    };

    mockAuthService.getCurrentUser.mockReturnValue(of(mockUserResponse));
    mockConversationService.getConversations.mockReturnValue(of(mockConversationsResponse));

    component.loadCurrentUser();

    expect(component.isLoading()).toBeFalsy();
    expect(component.currentUser()).toEqual(mockUserResponse.data.user);
    expect(mockConversationService.getConversations).toHaveBeenCalled();
  });

  it('should handle user loading error with 401', () => {
    // Reset spies
    mockAuthService.getCurrentUser.mockClear();
    mockRouter.navigate.mockClear();

    const errorResponse = {
      status: 401
    };

    mockAuthService.getCurrentUser.mockReturnValue(throwError(() => errorResponse));

    component.loadCurrentUser();

    expect(component.isLoading()).toBeFalsy();
    expect(mockRouter.navigate).toHaveBeenCalledWith(['/login']);
  });

  it('should handle user loading error with other status', () => {
    // Reset spies
    mockAuthService.getCurrentUser.mockClear();

    const errorResponse = {
      status: 500
    };

    mockAuthService.getCurrentUser.mockReturnValue(throwError(() => errorResponse));

    component.loadCurrentUser();

    expect(component.isLoading()).toBeFalsy();
    expect(component.isError()).toBeTruthy();
    expect(component.errorMessage()).toBe('Failed to load user data. Please try again.');
  });

  it('should load conversations after current user loads successfully', () => {
    // Reset spies
    mockAuthService.getCurrentUser.mockClear();
    mockConversationService.getConversations.mockClear();

    const mockUserResponse = {
      data: {
        user: {
          id: 1,
          name: 'Test User',
          email: 'test@example.com',
          email_verified_at: null,
          created_at: '2023-01-01',
          updated_at: '2023-01-01'
        }
      }
    };

    const mockConversationsResponse = {
      data: [
        {
          id: 1,
          title: 'Test Conversation',
          created_at: '2023-01-01',
          updated_at: '2023-01-01'
        }
      ]
    };

    mockAuthService.getCurrentUser.mockReturnValue(of(mockUserResponse));
    mockConversationService.getConversations.mockReturnValue(of(mockConversationsResponse));

    component.loadCurrentUser();

    expect(mockConversationService.getConversations).toHaveBeenCalled();
    expect(component.conversations()).toEqual(mockConversationsResponse.data);
  });

  it('should render conversations in the sidebar', () => {
    const mockConversations: Conversation[] = [
      {
        id: 1,
        title: 'Test Conversation 1',
        created_at: '2023-01-01',
        updated_at: '2023-01-01'
      },
      {
        id: 2,
        title: 'Test Conversation 2',
        created_at: '2023-01-02',
        updated_at: '2023-01-02'
      }
    ];

    component.conversations.set(mockConversations);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const conversationElements = compiled.querySelectorAll('.space-y-1 button');

    expect(conversationElements.length).toBe(2);
    expect(conversationElements[0].textContent).toContain('Test Conversation 1');
    expect(conversationElements[1].textContent).toContain('Test Conversation 2');
  });

  it('should render "New conversation" for null or empty titles', () => {
    const mockConversations: Conversation[] = [
      {
        id: 1,
        title: null,
        created_at: '2023-01-01',
        updated_at: '2023-01-01'
      },
      {
        id: 2,
        title: '',
        created_at: '2023-01-02',
        updated_at: '2023-01-02'
      }
    ];

    component.conversations.set(mockConversations);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const conversationElements = compiled.querySelectorAll('.space-y-1 button');

    expect(conversationElements.length).toBe(2);
    expect(conversationElements[0].textContent).toContain('New conversation');
    expect(conversationElements[1].textContent).toContain('New conversation');
  });

  it('should select conversation when clicked', () => {
    const mockConversation: Conversation = {
      id: 1,
      title: 'Test Conversation',
      created_at: '2023-01-01',
      updated_at: '2023-01-01'
    };

    component.selectConversation(mockConversation);

    expect(component.selectedConversation()).toEqual(mockConversation);
  });

  it('should call createConversation with null when creating new conversation', () => {
    // Reset spy
    mockConversationService.createConversation.mockClear();

    const mockResponse = {
      data: {
        id: 1,
        title: null,
        created_at: '2023-01-01',
        updated_at: '2023-01-01'
      }
    };

    mockConversationService.createConversation.mockReturnValue(of(mockResponse));

    component.createNewConversation();

    expect(mockConversationService.createConversation).toHaveBeenCalledWith(null);
  });

  it('should prepend and select new conversation on successful creation', () => {
    // Reset spy
    mockConversationService.createConversation.mockClear();

    const existingConversation: Conversation = {
      id: 1,
      title: 'Existing Conversation',
      created_at: '2023-01-01',
      updated_at: '2023-01-01'
    };

    const newConversation: Conversation = {
      id: 2,
      title: null,
      created_at: '2023-01-02',
      updated_at: '2023-01-02'
    };

    const mockResponse = {
      data: newConversation
    };

    component.conversations.set([existingConversation]);
    mockConversationService.createConversation.mockReturnValue(of(mockResponse));

    component.createNewConversation();

    expect(component.conversations().length).toBe(2);
    expect(component.conversations()[0]).toEqual(newConversation);
    expect(component.conversations()[1]).toEqual(existingConversation);
    expect(component.selectedConversation()).toEqual(newConversation);
  });

  it('should prevent duplicate conversation creation requests', () => {
    // Reset spy
    mockConversationService.createConversation.mockClear();

    // Use an observable that never emits to simulate a pending request
    const delayedObservable = new Observable(() => {}); // Never emits
    mockConversationService.createConversation.mockReturnValue(delayedObservable);

    // Call the handler twice
    component.createNewConversation();
    component.createNewConversation();

    // Verify createConversation was called exactly once
    expect(mockConversationService.createConversation).toHaveBeenCalledTimes(1);
    expect(mockConversationService.createConversation).toHaveBeenCalledWith(null);
  });

  it('should display retry button and reload conversations on failure', () => {
    // Reset spy
    mockConversationService.getConversations.mockClear();

    mockConversationService.getConversations.mockReturnValue(throwError(() => new Error('Network error')));

    component.loadConversations();

    expect(component.conversationsError()).toBeTruthy();

    // Reset the spy and set up a successful response for retry
    const mockConversationsResponse = {
      data: [
        {
          id: 1,
          title: 'Test Conversation',
          created_at: '2023-01-01',
          updated_at: '2023-01-01'
        }
      ]
    };

    mockConversationService.getConversations.mockReturnValue(of(mockConversationsResponse));

    component.loadConversations();

    expect(component.conversationsError()).toBeFalsy();
    expect(component.conversations()).toEqual(mockConversationsResponse.data);
  });

  it('should preserve existing conversations on creation failure', () => {
    // Reset spy
    mockConversationService.createConversation.mockClear();

    const existingConversation: Conversation = {
      id: 1,
      title: 'Existing Conversation',
      created_at: '2023-01-01',
      updated_at: '2023-01-01'
    };

    component.conversations.set([existingConversation]);
    component.selectedConversation.set(existingConversation);

    mockConversationService.createConversation.mockReturnValue(throwError(() => new Error('Network error')));

    component.createNewConversation();

    expect(component.conversations().length).toBe(1);
    expect(component.conversations()[0]).toEqual(existingConversation);
    expect(component.selectedConversation()).toEqual(existingConversation);
  });

  it('should close mobile sidebar when selecting a conversation', () => {
    component.isSidebarOpen.set(true);

    const mockConversation: Conversation = {
      id: 1,
      title: 'Test Conversation',
      created_at: '2023-01-01',
      updated_at: '2023-01-01'
    };

    component.selectConversation(mockConversation);

    expect(component.isSidebarOpen()).toBeFalsy();
  });

  it('should logout successfully', () => {
    // Reset spies
    mockAuthService.logout.mockClear();
    mockRouter.navigate.mockClear();

    const mockResponse = {};

    mockAuthService.logout.mockReturnValue(of(mockResponse));

    component.logout();

    expect(component.isLoggingOut()).toBeFalsy();
    expect(mockRouter.navigate).toHaveBeenCalledWith(['/login'], { replaceUrl: true });
  });

  it('should handle logout error', () => {
    // Reset spies
    mockAuthService.logout.mockClear();

    mockAuthService.logout.mockReturnValue(throwError(() => new Error('Network error')));

    component.logout();

    expect(component.isLoggingOut()).toBeFalsy();
    expect(component.errorMessage()).toBe('Unable to sign out. Please try again.');
  });
});