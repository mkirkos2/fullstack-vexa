import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { MessageService, Message, MessageCollectionResponse, MessageResponse } from './message.service';

describe('MessageService', () => {
  let service: MessageService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [MessageService]
    });

    service = TestBed.inject(MessageService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('should get messages for a conversation', () => {
    const conversationId = 1;
    const mockResponse: MessageCollectionResponse = {
      data: [
        {
          id: 1,
          role: 'user',
          content: 'Hello',
          created_at: '2023-01-01',
          updated_at: '2023-01-01'
        },
        {
          id: 2,
          role: 'assistant',
          content: 'Hi there!',
          created_at: '2023-01-01',
          updated_at: '2023-01-01'
        }
      ]
    };

    service.getMessages(conversationId).subscribe(response => {
      expect(response).toEqual(mockResponse);
      expect(response.data.length).toBe(2);
      expect(response.data[0].role).toBe('user');
      expect(response.data[1].role).toBe('assistant');
    });

    const req = httpMock.expectOne(`/api/conversations/${conversationId}/messages`);
    expect(req.request.method).toBe('GET');
    expect(req.request.withCredentials).toBe(true);
    req.flush(mockResponse);
  });

  it('should create a message in a conversation', () => {
    const conversationId = 1;
    const content = 'Hello, world!';
    const mockResponse: MessageResponse = {
      data: {
        id: 1,
        role: 'user',
        content: content,
        created_at: '2023-01-01',
        updated_at: '2023-01-01'
      }
    };

    service.createMessage(conversationId, content).subscribe(response => {
      expect(response).toEqual(mockResponse);
      expect(response.data.role).toBe('user');
      expect(response.data.content).toBe(content);
    });

    const req = httpMock.expectOne(`/api/conversations/${conversationId}/messages`);
    expect(req.request.method).toBe('POST');
    expect(req.request.withCredentials).toBe(true);
    expect(req.request.body).toEqual({ content });
    req.flush(mockResponse);
  });

  it('should generate an AI reply for a conversation', () => {
    const conversationId = 1;
    const mockResponse: MessageResponse = {
      data: {
        id: 2,
        role: 'assistant',
        content: 'Hello! How can I help you today?',
        created_at: '2023-01-01',
        updated_at: '2023-01-01'
      }
    };

    service.generateAiReply(conversationId).subscribe(response => {
      expect(response).toEqual(mockResponse);
      expect(response.data.role).toBe('assistant');
      expect(response.data.content).toBe('Hello! How can I help you today?');
    });

    const req = httpMock.expectOne(`/api/conversations/${conversationId}/ai-reply`);
    expect(req.request.method).toBe('POST');
    expect(req.request.withCredentials).toBe(true);
    expect(req.request.body).toEqual({});
    req.flush(mockResponse);
  });
});