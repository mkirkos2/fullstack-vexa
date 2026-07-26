import { Component, OnInit, signal } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService, LoginCredentials } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './login.component.html',
  styleUrl: './login.component.css'
})
export class LoginComponent implements OnInit {
  loginForm!: FormGroup;
  
  isLoading = signal(false);
  errorMessage = signal('');

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.loginForm = this.fb.nonNullable.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required]]
    });
  }

  onSubmit() {
    if (this.loginForm.invalid || this.isLoading()) {
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');

    const credentials: LoginCredentials = this.loginForm.getRawValue();
    
    this.authService.login(credentials).subscribe({
      next: () => {
        this.isLoading.set(false);
        // Navigate to dashboard after successful login
        this.router.navigate(['/dashboard']);
      },
      error: (error) => {
        this.isLoading.set(false);
        if (error.status === 401) {
          this.errorMessage.set('Invalid credentials. Please check your email and password.');
        } else if (error.status === 422) {
          // Handle validation errors
          if (error.error && error.error.errors) {
            const errors = error.error.errors;
            let errorMsg = '';
            for (const field in errors) {
              if (errors.hasOwnProperty(field)) {
                errorMsg += `${errors[field][0]} `;
              }
            }
            this.errorMessage.set(errorMsg.trim());
          } else {
            this.errorMessage.set('Please check your input and try again.');
          }
        } else {
          this.errorMessage.set('An unexpected error occurred. Please try again later.');
        }
      }
    });
  }
}