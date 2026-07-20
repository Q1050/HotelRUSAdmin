import { Link, usePage } from '@inertiajs/react';
import { Activity, Building2, ChevronRight, CreditCard, Hotel, LayoutDashboard, LogOut, Menu, Network, ShieldCheck, X } from 'lucide-react';
import { ReactNode, useState } from 'react';

export function PlatformLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const [mobileOpen, setMobileOpen] = useState(false);
    const user = page.props.auth.user as unknown as { name?: string; firstName?: string; email?: string };
    const system = page.props.system as { version:string; releaseName:string };
    const path = typeof window === 'undefined' ? page.url : window.location.pathname;
    const nav = (href:string,label:string,icon:ReactNode,active:boolean) => <Link href={href} className={`flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition ${active?'bg-white/10 text-white ring-1 ring-white/10':'text-slate-300 hover:bg-white/5 hover:text-white'}`}>{icon}<span className="flex-1">{label}</span>{active&&<ChevronRight size={15} className="text-slate-500"/>}</Link>;

    const sidebar = <div className="flex h-full flex-col">
        <div className="flex items-center justify-between border-b border-slate-800 px-5 py-5">
            <div className="flex items-center gap-3"><div className="rounded-xl bg-amber-400 p-2.5 text-slate-950"><Building2 size={21}/></div><div><p className="font-bold leading-tight text-white">HotelKey</p><p className="mt-0.5 text-xs text-slate-400">Platform console</p></div></div>
            <button onClick={() => setMobileOpen(false)} className="rounded-lg p-2 text-slate-400 hover:bg-slate-800 lg:hidden"><X size={19}/></button>
        </div>
        <nav className="flex-1 space-y-1 p-3">
            <p className="mb-2 px-3 pt-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Management</p>
            {nav(route('platform.overview'),'Overview',<LayoutDashboard size={18}/>,path==='/platform'||path==='/platform/')}
            {nav(route('platform.hotels.index'),'Client hotels',<Hotel size={18}/>,path.startsWith('/platform/hotels'))}
            {nav(route('platform.organizations.index'),'Hotel groups',<Network size={18}/>,path.startsWith('/platform/organizations'))}
            {nav(route('platform.plans.index'),'Plans & modules',<CreditCard size={18}/>,path.startsWith('/platform/plans'))}
            {nav(route('platform.billing.index'),'Billing',<CreditCard size={18}/>,path.startsWith('/platform/billing'))}
            {nav(route('platform.activity.index'),'Platform activity',<Activity size={18}/>,path.startsWith('/platform/activity'))}
            <p className="mb-2 px-3 pt-6 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Workspace</p>
            <a href={route('dashboard')} className="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white"><LayoutDashboard size={18}/><span>Hotel dashboard</span></a>
        </nav>
        <div className="m-3 rounded-2xl border border-slate-700 bg-slate-900/80 p-4">
            <div className="flex items-center gap-2 text-xs font-semibold text-emerald-400"><ShieldCheck size={15}/>Platform administrator</div>
            <p className="mt-2 truncate text-sm font-medium text-white">{user.name ?? user.firstName}</p><p className="truncate text-xs text-slate-500">{user.email}</p>
            <div className="mt-4 border-t border-slate-800 pt-3"><p className="text-[11px] text-slate-500">v{system.version}</p><p className="text-xs text-slate-400">{system.releaseName}</p></div>
            <Link href={route('logout')} method="post" as="button" className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-700 px-3 py-2.5 text-sm font-semibold text-slate-300 transition hover:border-slate-600 hover:bg-slate-800 hover:text-white"><LogOut size={16}/>Logout</Link>
        </div>
    </div>;

    return <div className="min-h-screen w-full bg-[#f4f6f9] text-left text-slate-900">
        <aside className="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-slate-800 bg-slate-950 lg:block" style={{backgroundColor:'#020617'}}>{sidebar}</aside>
        {mobileOpen && <div className="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm lg:hidden" onMouseDown={event => event.target === event.currentTarget && setMobileOpen(false)}><aside className="h-full w-72 bg-slate-950 shadow-2xl" style={{backgroundColor:'#020617'}}>{sidebar}</aside></div>}
        <div className="relative min-h-screen bg-[#f4f6f9] lg:ml-72">
            <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/90 px-5 py-3 backdrop-blur lg:px-8"><div className="mx-auto flex max-w-[1180px] items-center justify-between"><div className="flex items-center gap-3"><button onClick={() => setMobileOpen(true)} className="rounded-xl border border-slate-200 p-2.5 text-slate-700 lg:hidden"><Menu size={20}/></button><div><p className="text-sm font-bold text-slate-900">Platform administration</p><p className="hidden text-xs text-slate-500 sm:block">Manage your client properties and subscriptions</p></div></div><a href={route('dashboard')} className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"><LayoutDashboard size={16}/><span className="hidden sm:inline">Hotel dashboard</span></a></div></header>
            <main className="mx-auto w-full max-w-[1180px] px-5 py-7 lg:px-8 lg:py-9">{children}</main>
        </div>
    </div>;
}
