
import { useState } from "react";
import { Link } from "@inertiajs/react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { 
  Home, 
  Users, 
  Bed, 
  Settings, 
  LogOut, 
  Menu, 
  X, 
  Lock, 
  Plus
} from "lucide-react";

interface SidebarLinkProps {
  to: string;
  icon: React.ReactNode;
  children: React.ReactNode;
  isActive?: boolean;
  isCollapsed?: boolean;
}

const SidebarLink = ({ to, icon, children, isActive = false, isCollapsed = false }: SidebarLinkProps) => {
  return (
    <Link
      href={to}
      className={cn(
        "flex items-center gap-3 px-3 py-2 rounded-md transition-colors",
        isActive 
          ? "bg-sidebar-accent text-sidebar-accent-foreground" 
          : "text-sidebar-foreground/80 hover:bg-sidebar-accent/50 hover:text-sidebar-accent-foreground",
        isCollapsed && "justify-center"
      )}
    >
      {icon}
      {!isCollapsed && <span>{children}</span>}
    </Link>
  );
};

export const Sidebar = () => {
  const [isCollapsed, setIsCollapsed] = useState(false);
  
  const currentPath = window.location.pathname;
  
  return (
    <div
      className={cn(
        "bg-sidebar h-screen fixed left-0 top-0 z-30 flex flex-col border-r border-sidebar-border transition-all duration-300",
        isCollapsed ? "w-16" : "w-64"
      )}
    >
      <div className="flex items-center justify-between p-4 border-b border-sidebar-border">
        {!isCollapsed && (
          <div className="flex items-center gap-2">
            <Lock className="text-hotel-gold" size={24} />
            <h1 className="text-xl font-bold text-white">HotelKey</h1>
          </div>
        )}
        <Button
          variant="ghost"
          size="icon"
          className="text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground md:hidden"
          onClick={() => setIsCollapsed(!isCollapsed)}
        >
          {isCollapsed ? <Menu size={20} /> : <X size={20} />}
        </Button>
      </div>
      
      <div className="flex flex-col gap-1 p-2 flex-1">
        <SidebarLink 
          to="/dashboard/" 
          icon={<Home size={20} />} 
          isActive={currentPath === "/dashboard/"} 
          isCollapsed={isCollapsed}
        >
          Dashboard
        </SidebarLink>
        <SidebarLink 
          to="/dashboard/guests" 
          icon={<Users size={20} />} 
          isActive={currentPath === "/dashboard/guests"} 
          isCollapsed={isCollapsed}
        >
          Guest Check-ins
        </SidebarLink>
        <SidebarLink 
          to="/dashboard/rooms" 
          icon={<Bed size={20} />} 
          isActive={currentPath === "/dashboard/rooms"} 
          isCollapsed={isCollapsed}
        >
          Rooms
        </SidebarLink>
        <SidebarLink 
          to="/dashboard/settings" 
          icon={<Settings size={20} />} 
          isActive={currentPath === "/dashboard/settings"} 
          isCollapsed={isCollapsed}
        >
          Settings
        </SidebarLink>
      </div>
      
      <div className="p-2 border-t border-sidebar-border">
        <SidebarLink 
          to="/dashboard/logout" 
          icon={<LogOut size={20} />} 
          isCollapsed={isCollapsed}
        >
          Logout
        </SidebarLink>
      </div>
    </div>
  );
};

export const AddButton = ({ children, onClick }: { children: React.ReactNode, onClick?: () => void }) => {
  return (
    <Button onClick={onClick} className="flex items-center gap-2 bg-hotel-navy hover:bg-hotel-navy/90">
      <Plus size={16} />
      <span>{children}</span>
    </Button>
  );
};
