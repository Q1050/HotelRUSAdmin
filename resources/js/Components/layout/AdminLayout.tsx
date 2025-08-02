
import { ReactNode, useState } from "react";
import { cn } from "@/lib/utils";
import { Sidebar } from "../Sidebar";
import { SidebarProvider } from '@/components/ui/sidebar'
interface AdminLayoutProps {
  children: ReactNode;
  title?: string;
  user? :UserModel;
}

export const AdminLayout = ({ children, title, user }: AdminLayoutProps) => {
  const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false);
  return (
    <SidebarProvider>
    <div className="flex h-screen w-full bg-hotel-light-beige overflow-hidden">
      {/* Desktop Sidebar */}
      <div className="hidden md:block">
        <Sidebar />
      </div>
      
      {/* Mobile Sidebar */}
      <div className={cn(
        "md:hidden fixed inset-0 bg-black bg-opacity-50 z-40 transition-opacity",
        isMobileSidebarOpen ? "opacity-100" : "opacity-0 pointer-events-none"
      )}>
        <div className={cn(
          "fixed inset-y-0 left-0 w-64 transition-transform transform",
          isMobileSidebarOpen ? "translate-x-0" : "-translate-x-full"
        )}>
          <Sidebar />
        </div>
      </div>
      
      {/* Main Content */}
      <div className="flex-1 flex flex-col md:ml-64">
        {/* Top Bar */}
        <header className="bg-white shadow-sm p-4">
          <div className="flex items-center justify-between">
            <button 
              className="md:hidden p-2 rounded-md text-hotel-navy"
              onClick={() => setIsMobileSidebarOpen(!isMobileSidebarOpen)}
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              </svg>
            </button>
            {title && (
              <h1 className="text-xl font-semibold text-hotel-navy">{title}</h1>
            )}
            <div className="flex items-center gap-3">
              <span className="text-sm font-medium text-gray-600">{user?.firstName} {user?.lastName}</span>
              <div className="w-8 h-8 bg-hotel-navy text-white rounded-full flex items-center justify-center">
                {user?.initials || "U"}
              </div>
            </div>
          </div>
        </header>
        
        {/* Content */}
        <main className="flex-1 overflow-auto p-6">
          {children}
        </main>
      </div>
    </div>
    </SidebarProvider>
  );
};
