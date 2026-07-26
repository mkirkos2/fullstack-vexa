import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DashboardComponent } from './dashboard.component';

describe('DashboardComponent', () => {
  let component: DashboardComponent;
  let fixture: ComponentFixture<DashboardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DashboardComponent]
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
});