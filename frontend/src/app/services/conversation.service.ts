import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

// Interfaces for conversations
export interface Conversation {
  id: number;
  title: string | null;
  created_at: string;
  updated_at: string;
}

export interface ConversationCollectionResponse {
  data: Conversation[];
}

export interface ConversationResponse {
  data: Conversation;
}

@Injectable({
  providedIn: 'root'
})
export class ConversationService {
  constructor(private http: HttpClient) {}

  /**
   * Get all conversations for the current user
   */
  getConversations(): Observable<ConversationCollectionResponse> {
    return this.http.get<ConversationCollectionResponse>(`/api/conversations`, {
      withCredentials: true
    });
  }

  /**
   * Create a new conversation
   */
  createConversation(title: string | null = null): Observable<ConversationResponse> {
    return this.http.post<ConversationResponse>(`/api/conversations`, { title }, {
      withCredentials: true
    });
  }
}