import { Component, OnInit, signal } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService, User } from '../../services/auth.service';

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
  isLoggingOut = signal(false);
  errorMessage = signal('');
  isSidebarOpen = signal(false);

  constructor(
    private authService: AuthService,
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