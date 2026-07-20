import { useEffect, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search } from 'lucide-react';

type GuestResult = { id: number; name: string; email: string | null; phone: string | null; idStatus: string };

export function GuestCheckinModal({ open, onClose, onCreateAccount }: { open: boolean; onClose: () => void; onCreateAccount: () => void }) {
  const [term, setTerm] = useState('');
  const [guests, setGuests] = useState<GuestResult[]>([]);
  const [loading, setLoading] = useState(false);
  useEffect(() => {
    if (!open) return;
    const timer = window.setTimeout(async () => {
      setLoading(true);
      try { const response = await axios.get(route('dashboard.guests.search'), { params: { q: term } }); setGuests(response.data.guests); }
      finally { setLoading(false); }
    }, 200);
    return () => window.clearTimeout(timer);
  }, [open, term]);
  if (!open) return null;
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onMouseDown={onClose}><div onMouseDown={e => e.stopPropagation()} className="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl"><h2 className="text-xl font-semibold">Start guest check-in</h2><p className="mt-1 text-sm text-gray-600">A guest account is required. Search for the guest before assigning a room.</p><div className="relative mt-4"><Search className="absolute left-3 top-3 text-gray-400" size={18}/><Input autoFocus className="pl-10" placeholder="Search by name, email, or phone…" value={term} onChange={e => setTerm(e.target.value)}/></div><div className="mt-3 max-h-72 divide-y overflow-auto rounded-md border">{loading && <p className="p-4 text-center text-gray-500">Searching…</p>}{!loading && guests.map(guest => <button type="button" key={guest.id} className="flex w-full items-center justify-between p-3 text-left hover:bg-gray-50" onClick={() => router.visit(route('dashboard.guests.show', guest.id))}><span><strong className="block">{guest.name}</strong><span className="text-sm text-gray-500">{guest.email || guest.phone || 'No contact details'}</span></span><span className="text-sm text-hotel-navy">Select</span></button>)}{!loading && guests.length === 0 && <p className="p-4 text-center text-gray-500">No guest account found.</p>}</div><div className="mt-5 flex items-center justify-between"><Button type="button" variant="outline" onClick={() => { onClose(); onCreateAccount(); }}>Create guest account</Button><Button type="button" variant="ghost" onClick={onClose}>Cancel</Button></div></div></div>;
}
