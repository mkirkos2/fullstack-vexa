import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

// Interfaces for messages
export interface Message {
  id: number;
  role: 'user' | 'assistant' | 'system';
  content: string;
  created_at: string;
  updated_at: string;
}

export interface MessageCollectionResponse {
  data: Message[];
}

export interface MessageResponse {
  data: Message;
}

@Injectable({
  providedIn: 'root'
})
export class MessageService {
  constructor(private http: HttpClient) {}

  /**
   * Get all messages for a conversation
   */
  getMessages(conversationId: number): Observable<MessageCollectionResponse> {
    return this.http.get<MessageCollectionResponse>(`/api/conversations/${conversationId}/messages`, {
      withCredentials: true
    });
  }

  /**
   * Create a new message in a conversation
   */
  createMessage(conversationId: number, content: string): Observable<MessageResponse> {
    return this.http.post<MessageResponse>(`/api/conversations/${conversationId}/messages`, { content }, {
      withCredentials: true
    });
  }
}