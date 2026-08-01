import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

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

  getMessages(conversationId: number): Observable<MessageCollectionResponse> {
    return this.http.get<MessageCollectionResponse>(`/api/conversations/${conversationId}/messages`, {
      withCredentials: true
    });
  }

  createMessage(conversationId: number, content: string): Observable<MessageResponse> {
    return this.http.post<MessageResponse>(`/api/conversations/${conversationId}/messages`, { content }, {
      withCredentials: true
    });
  }

  generateAiReply(conversationId: number): Observable<MessageResponse> {
    return this.http.post<MessageResponse>(`/api/conversations/${conversationId}/ai-reply`, {}, {
      withCredentials: true
    });
  }
}