import { FormEvent, useState } from 'react';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { AdminLayout } from '@/components/layout/AdminLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, RefreshCw, Search } from 'lucide-react';
import { GuestFormModal } from '@/components/GuestFormModal';

type Role = 'super_admin' | 'manager' | 'front_desk' | 'housekeeping' | 'maintenance';
type UserTab = 'all' | Role | 'guests';
type Staff = { id: number; firstName: string; lastName: string; email: string; role: Role; roleName:string|null;staffRoleId:number|null;status: 'active' | 'suspended'; lastLoginAt: string | null; createdAt: string | null };
type CustomRole={id:number;name:string;baseRole:Role;permissions:string[]};
type GuestAccount = { id: number; name: string; email: string | null; phone: string | null; idStatus: string; createdAt: string | null };

export default function Users({ users, guests, roles }: { users: Staff[]; guests: GuestAccount[];roles:CustomRole[] }) {
  const currentUser = usePage().props.auth.user;
  const [search, setSearch] = useState('');
  const [activeTab, setActiveTab] = useState<UserTab>('all');
  const [editing, setEditing] = useState<Staff | null>(null);
  const [open, setOpen] = useState(false);
  const [guestOpen, setGuestOpen] = useState(false);
  const form = useForm({ firstName: '', lastName: '', email: '', role: 'front_desk' as Role,staff_role_id:'' as number|'', status: 'active' as 'active' | 'suspended', password: '', password_confirmation: '', change_reason: '' });
  const filtered = users.filter(u =>
    (activeTab === 'all' || u.role === activeTab) &&
    `${u.firstName} ${u.lastName} ${u.email} ${u.role}`.toLowerCase().includes(search.toLowerCase())
  );
  const filteredGuests = guests.filter(guest => `${guest.name} ${guest.email ?? ''} ${guest.phone ?? ''}`.toLowerCase().includes(search.toLowerCase()));
  const tabs: Array<{ value: UserTab; label: string }> = [
    { value: 'all', label: 'All Staff' },
    { value: 'super_admin', label: 'Admins' },
    { value: 'manager', label: 'Managers' },
    { value: 'front_desk', label: 'Front Desk' },
    { value: 'housekeeping', label: 'Housekeeping' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'guests', label: 'Guests' },
  ];
  const showCreate = () => { setEditing(null); form.reset(); form.setData({ firstName: '', lastName: '', email: '', role: 'front_desk',staff_role_id:'', status: 'active', password: '', password_confirmation: '', change_reason: '' }); setOpen(true); };
  const showEdit = (user: Staff) => { setEditing(user); form.setData({ firstName: user.firstName, lastName: user.lastName, email: user.email, role: user.role,staff_role_id:user.staffRoleId??'', status: user.status, password: '', password_confirmation: '', change_reason: '' }); setOpen(true); };
  const submit = (event: FormEvent) => { event.preventDefault(); let reason=''; if(editing&&(editing.role!==form.data.role||editing.status!==form.data.status)){reason=window.prompt('Reason for changing this staff member’s role or status:')?.trim()??'';if(!reason)return;} form.transform(data=>({...data,change_reason:reason})); const options = { preserveScroll: true, onSuccess: () => setOpen(false) }; editing ? form.patch(route('dashboard.users.update', editing.id), options) : form.post(route('dashboard.users.store'), options); };

  return <AdminLayout title="Manage Users">
    <div className="mb-6 flex flex-wrap items-center justify-between gap-3"><div><h1 className="text-2xl font-bold">Manage accounts</h1><p className="text-gray-600">Manage staff access and find registered guests.</p></div><div className="flex gap-2"><Link href={route('dashboard.roles.index')}><Button variant="outline">Roles & permissions</Button></Link><Button variant="outline" onClick={() => router.reload({ only: ['users', 'guests','roles'] })}><RefreshCw className="mr-2" size={16}/>Refresh</Button><Button onClick={activeTab === 'guests' ? () => setGuestOpen(true) : showCreate}><Plus className="mr-2" size={16}/>{activeTab === 'guests' ? 'Add guest account' : 'Add staff user'}</Button></div></div>
    <div className="mb-4 rounded-lg bg-white shadow">
      <div className="flex overflow-x-auto border-b px-3 pt-2">{tabs.map(tab => <button key={tab.value} type="button" onClick={() => setActiveTab(tab.value)} className={`whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium ${activeTab === tab.value ? 'border-hotel-navy text-hotel-navy' : 'border-transparent text-gray-500 hover:text-gray-800'}`}>{tab.label}<span className="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs">{tab.value === 'all' ? users.length : tab.value === 'guests' ? guests.length : users.filter(user => user.role === tab.value).length}</span></button>)}</div>
      <div className="p-4"><div className="relative"><Search className="absolute left-3 top-3 text-gray-400" size={18}/><Input className="pl-10" placeholder={`Search ${tabs.find(tab => tab.value === activeTab)?.label.toLowerCase()}…`} value={search} onChange={(e) => setSearch(e.target.value)}/></div></div>
    </div>
    {activeTab === 'guests' ? <div className="overflow-x-auto rounded-lg bg-white shadow"><table className="min-w-full divide-y"><thead className="bg-gray-50"><tr>{['Guest','Email','Phone','ID Status','Created','Actions'].map(h => <th key={h} className="px-4 py-3 text-left text-xs uppercase text-gray-500">{h}</th>)}</tr></thead><tbody className="divide-y">{filteredGuests.map(guest => <tr key={guest.id}><td className="px-4 py-3 font-medium">{guest.name}</td><td className="px-4 py-3">{guest.email || '—'}</td><td className="px-4 py-3">{guest.phone || '—'}</td><td className="px-4 py-3 capitalize">{guest.idStatus}</td><td className="px-4 py-3 text-sm text-gray-600">{guest.createdAt ? new Date(guest.createdAt).toLocaleDateString() : '—'}</td><td className="px-4 py-3"><Button size="sm" variant="outline" onClick={() => router.visit(route('dashboard.guests.show', guest.id))}>View</Button></td></tr>)}{filteredGuests.length === 0 && <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-500">No guest accounts found.</td></tr>}</tbody></table></div> : <div className="overflow-x-auto rounded-lg bg-white shadow"><table className="min-w-full divide-y"><thead className="bg-gray-50"><tr>{['Name','Email','Role','Status','Last login','Actions'].map(h => <th key={h} className="px-4 py-3 text-left text-xs uppercase text-gray-500">{h}</th>)}</tr></thead><tbody className="divide-y">{filtered.map(user => <tr key={user.id}><td className="px-4 py-3 font-medium">{user.firstName} {user.lastName}{user.id === currentUser.id && <span className="ml-2 text-xs text-gray-500">You</span>}</td><td className="px-4 py-3">{user.email}</td><td className="px-4 py-3">{roleLabel(user.role)}</td><td className="px-4 py-3"><span className={`rounded-full px-2 py-1 text-xs ${user.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>{user.status}</span></td><td className="px-4 py-3 text-sm text-gray-600">{user.lastLoginAt ? new Date(user.lastLoginAt).toLocaleString() : 'Never'}</td><td className="px-4 py-3"><Button size="sm" variant="outline" onClick={() => showEdit(user)}>Edit</Button></td></tr>)}{filtered.length === 0 && <tr><td colSpan={6} className="px-4 py-10 text-center text-gray-500">No users found in this category.</td></tr>}</tbody></table></div>}
    {open && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onMouseDown={() => setOpen(false)}><form onSubmit={submit} onMouseDown={e => e.stopPropagation()} className="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl"><h2 className="mb-4 text-xl font-semibold">{editing ? 'Edit staff user' : 'Add staff user'}</h2><div className="grid gap-4 md:grid-cols-2"><Field label="First name" error={form.errors.firstName}><Input value={form.data.firstName} onChange={e => form.setData('firstName', e.target.value)} required/></Field><Field label="Last name" error={form.errors.lastName}><Input value={form.data.lastName} onChange={e => form.setData('lastName', e.target.value)} required/></Field><Field label="Email" error={form.errors.email}><Input type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} required/></Field><Field label="Role" error={form.errors.role}><select className="w-full rounded-md border p-2" value={form.data.role} onChange={e => form.setData('role', e.target.value as Role)}><option value="super_admin">Super Admin</option><option value="manager">Manager</option><option value="front_desk">Front Desk</option><option value="housekeeping">Housekeeping</option><option value="maintenance">Maintenance Technician</option></select></Field><Field label="Status" error={form.errors.status}><select className="w-full rounded-md border p-2" value={form.data.status} disabled={editing?.id === currentUser.id} onChange={e => form.setData('status', e.target.value as 'active'|'suspended')}><option value="active">Active</option><option value="suspended">Suspended</option></select></Field><div/><Field label={editing ? 'New password (optional)' : 'Password'} error={form.errors.password}><Input type="password" value={form.data.password} onChange={e => form.setData('password', e.target.value)} required={!editing}/></Field><Field label="Confirm password" error={form.errors.password_confirmation}><Input type="password" value={form.data.password_confirmation} onChange={e => form.setData('password_confirmation', e.target.value)} required={!editing || !!form.data.password}/></Field></div>{form.errors.role && <p className="mt-3 text-sm text-red-600">{form.errors.role}</p>}<div className="mt-6 flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setOpen(false)}>Cancel</Button><Button disabled={form.processing}>{editing ? 'Save changes' : 'Create user'}</Button></div></form></div>}
    <GuestFormModal open={guestOpen} onClose={() => setGuestOpen(false)} />
  </AdminLayout>;
}

const roleLabel = (role: Role) => ({ super_admin: 'Super Admin', manager: 'Manager', front_desk: 'Front Desk', housekeeping: 'Housekeeping', maintenance: 'Maintenance Technician' })[role];
function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) { return <label className="space-y-1 text-sm font-medium"><span>{label}</span>{children}{error && <span className="block text-red-600">{error}</span>}</label>; }
