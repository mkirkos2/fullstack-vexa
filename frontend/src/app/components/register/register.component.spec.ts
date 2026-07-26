import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RegisterComponent } from './register.component';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';

describe('RegisterComponent', () => {
  let component: RegisterComponent;
  let fixture: ComponentFixture<RegisterComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RegisterComponent, ReactiveFormsModule],
      providers: [
        FormBuilder
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(RegisterComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should initialize form with required fields', () => {
    expect(component.registerForm.contains('name')).toBeTruthy();
    expect(component.registerForm.contains('email')).toBeTruthy();
    expect(component.registerForm.contains('password')).toBeTruthy();
    expect(component.registerForm.contains('password_confirmation')).toBeTruthy();
  });

  it('should make form invalid when fields are empty', () => {
    const form = component.registerForm;
    expect(form.valid).toBeFalsy();
  });

  it('should validate password confirmation', () => {
    const form = component.registerForm;
    form.controls['password'].setValue('password123');
    form.controls['password_confirmation'].setValue('differentpassword');
    form.updateValueAndValidity();
    expect(form.valid).toBeFalsy();
    expect(form.controls['password_confirmation'].errors?.['passwordMismatch']).toBeTruthy();
  });

  it('should make form valid when all fields are correctly filled', () => {
    const form = component.registerForm;
    form.controls['name'].setValue('John Doe');
    form.controls['email'].setValue('john@example.com');
    form.controls['password'].setValue('password123');
    form.controls['password_confirmation'].setValue('password123');
    form.updateValueAndValidity();
    expect(form.valid).toBeTruthy();
    // Note: Due to the way the validator is implemented, we can't easily check for null errors here
  });
});