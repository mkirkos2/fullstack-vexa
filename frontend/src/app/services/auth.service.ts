import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, switchMap } from 'rxjs';

// Interfaces for authentication
export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterCredentials {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface LoginResponse {
  data: {
    user: User;
  };
  message: string;
}

export interface RegisterResponse {
  data: {
    user: User;
  };
  message: string;
}

export interface UserResponse {
  data: {
    user: User;
  };
}

export interface LogoutResponse {
  message: string;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  constructor(private http: HttpClient) {}

  /**
   * Get CSRF cookie from Laravel
   */
  getCsrfCookie(): Observable<any> {
    return this.http.get(`/sanctum/csrf-cookie`, {
      withCredentials: true
    });
  }

  /**
   * Login user with email and password
   */
  login(credentials: LoginCredentials): Observable<LoginResponse> {
    return this.getCsrfCookie().pipe(
      switchMap(() =>
        this.http.post<LoginResponse>(`/api/login`, credentials, {
          withCredentials: true
        })
      )
    );
  }

  /**
   * Register a new user
   */
  register(credentials: RegisterCredentials): Observable<RegisterResponse> {
    return this.getCsrfCookie().pipe(
      switchMap(() =>
        this.http.post<RegisterResponse>(`/api/register`, credentials, {
          withCredentials: true
        })
      )
    );
  }

  /**
   * Get the current authenticated user
   */
  getCurrentUser(): Observable<UserResponse> {
    return this.http.get<UserResponse>(`/api/user`, {
      withCredentials: true
    });
  }

  /**
   * Logout the current user
   */
  logout(): Observable<LogoutResponse> {
    return this.http.post<LogoutResponse>(`/api/logout`, {}, {
      withCredentials: true
    });
  }
}