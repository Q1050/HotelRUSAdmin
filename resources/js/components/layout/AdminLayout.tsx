
import { ReactNode, useState } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import { Bell } from "lucide-react";
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
  const [showNotifications, setShowNotifications] = useState(false);
  const page = usePage();
  const currentUser = (user ?? page.props.auth.user) as unknown as { firstName?: string; lastName?: string; initials?: string; name?: string };
  const notifications = page.props.notifications;
  const subscription = (page.props.hotel as any)?.subscription as {status:string;readOnly:boolean;trialEndsAt:string|null;accessEndsAt:string|null;retentionDays:number}|null;
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
        {page.props.impersonating && <div className="flex items-center justify-between gap-4 bg-amber-400 px-5 py-2 text-sm font-semibold text-amber-950"><span>Platform support session: you are viewing this hotel as its administrator.</span><Link href={route('platform.impersonation.stop')} method="post" as="button" className="rounded bg-amber-950 px-3 py-1.5 text-white">Exit support session</Link></div>}
        {subscription && subscription.status !== 'active' && <div className={`flex flex-wrap items-center justify-between gap-3 px-5 py-2.5 text-sm font-semibold ${subscription.readOnly?'bg-red-100 text-red-900':'bg-indigo-100 text-indigo-950'}`}><span>{subscription.status==='trialing'&&subscription.trialEndsAt?`Free trial ends ${new Date(subscription.trialEndsAt).toLocaleDateString()}.`:subscription.status==='grace'&&subscription.accessEndsAt?`Trial expired. This hotel is read-only until ${new Date(subscription.accessEndsAt).toLocaleDateString()}.`:`Subscription expired. Data is retained for ${subscription.retentionDays} days; subscribe to restore changes.`}</span><span className="rounded-full bg-white/70 px-3 py-1 text-xs uppercase tracking-wide">{subscription.status}</span></div>}
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
              <div className="relative">
                <button className="relative rounded-full p-2 text-gray-600 hover:bg-gray-100" onClick={() => setShowNotifications(!showNotifications)} aria-label="Notifications">
                  <Bell size={20}/>{notifications.unreadCount > 0 && <span className="absolute right-0 top-0 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs text-white">{notifications.unreadCount}</span>}
                </button>
                {showNotifications && <div className="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-lg border bg-white shadow-xl"><div className="flex items-center justify-between border-b p-3"><strong className="text-sm">Notifications</strong>{notifications.unreadCount > 0 && <button className="text-xs text-hotel-navy" onClick={() => router.patch(route('dashboard.notifications.read'), {}, { preserveScroll: true })}>Mark all read</button>}</div><div className="max-h-80 divide-y">{notifications.latest.map(notification => notification.url ? <Link key={notification.id} href={notification.url} className={`block p-3 text-sm hover:bg-gray-50 ${notification.read ? 'text-gray-500' : 'font-medium text-gray-900'}`}>{notification.message}</Link> : <p key={notification.id} className="p-3 text-sm">{notification.message}</p>)}{notifications.latest.length === 0 && <p className="p-5 text-center text-sm text-gray-500">No notifications.</p>}</div></div>}
              </div>
              <span className="text-sm font-medium text-gray-600">{currentUser?.firstName} {currentUser?.lastName}</span>
              <div className="w-8 h-8 bg-hotel-navy text-white rounded-full flex items-center justify-center">
                {currentUser?.initials || currentUser?.name?.charAt(0) || "U"}
              </div>
            </div>
          </div>
        </header>
        
        {/* Content */}
        <main className="flex-1 overflow-auto p-6 w-full">
          {children}
        </main>
      </div>
    </div>
    </SidebarProvider>
  );
};
