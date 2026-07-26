import { Component, OnInit, signal } from '@angular/core';
import { AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, ValidatorFn, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService, RegisterCredentials } from '../../services/auth.service';

interface ValidationErrors {
  [key: string]: any;
}

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [ReactiveFormsModule],
  templateUrl: './register.component.html',
  styleUrl: './register.component.css'
})
export class RegisterComponent implements OnInit {
  registerForm!: FormGroup;

  isLoading = signal(false);
  errorMessage = signal('');

  constructor(
    private fb: FormBuilder,
    private authService: AuthService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.registerForm = this.fb.nonNullable.group({
      name: ['', [Validators.required, Validators.maxLength(255)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      password_confirmation: ['', [Validators.required]]
    });

    // Add custom validator for password matching
    this.registerForm.addValidators(this.passwordMatchValidator);
  }

  passwordMatchValidator: ValidatorFn = (control: AbstractControl): ValidationErrors | null => {
    const password = control.get('password');
    const confirmPassword = control.get('password_confirmation');

    if (password && confirmPassword && password.value !== confirmPassword.value) {
      confirmPassword.setErrors({ passwordMismatch: true });
      return { passwordMismatch: true };
    } else if (confirmPassword?.errors?.['passwordMismatch']) {
      // Remove passwordMismatch error if passwords now match
      const { passwordMismatch, ...otherErrors } = confirmPassword.errors;
      confirmPassword.setErrors(Object.keys(otherErrors).length > 0 ? otherErrors : null);
    }

    return null;
  };

  onSubmit() {
    if (this.registerForm.invalid || this.isLoading()) {
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set('');

    const credentials: RegisterCredentials = this.registerForm.getRawValue();

    this.authService.register(credentials).subscribe({
      next: () => {
        this.isLoading.set(false);
        // Navigate to dashboard after successful registration
        this.router.navigate(['/dashboard']);
      },
      error: (error) => {
        this.isLoading.set(false);
        if (error.status === 422) {
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