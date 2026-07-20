import { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export function GuestFormModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    first_name: '', last_name: '', email: '', phone: '', address: '', id_type: '', id_number: '', notes: '',
  });
  if (!open) return null;
  const submit = (event: FormEvent) => {
    event.preventDefault();
    post(route('dashboard.guests.store'), { onSuccess: () => { reset(); onClose(); } });
  };
  return <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onMouseDown={onClose}>
    <form onSubmit={submit} onMouseDown={(e) => e.stopPropagation()} className="max-h-[90vh] w-full max-w-2xl overflow-auto rounded-lg bg-white p-6 shadow-xl">
      <h2 className="mb-4 text-xl font-semibold">Create guest account</h2>
      <div className="grid gap-4 md:grid-cols-2">
        <Field label="First name" error={errors.first_name}><Input value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} required /></Field>
        <Field label="Last name" error={errors.last_name}><Input value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} required /></Field>
        <Field label="Email" error={errors.email}><Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} /></Field>
        <Field label="Phone" error={errors.phone}><Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} /></Field>
        <Field label="ID type" error={errors.id_type}><select className="w-full rounded-md border border-gray-300 px-3 py-2" value={data.id_type} onChange={(e) => setData('id_type', e.target.value)}><option value="">Select ID type</option><option value="Passport">Passport</option><option value="Driver's Licence">Driver's Licence</option><option value="National ID">National ID</option><option value="Voter ID">Voter ID</option><option value="Other Government ID">Other Government ID</option></select></Field>
        <Field label="ID number" error={errors.id_number}><Input value={data.id_number} onChange={(e) => setData('id_number', e.target.value)} /></Field>
        <Field label="Address" error={errors.address}><Input value={data.address} onChange={(e) => setData('address', e.target.value)} /></Field>
        <Field label="Notes" error={errors.notes}><Input value={data.notes} onChange={(e) => setData('notes', e.target.value)} /></Field>
      </div>
      <p className="mt-3 text-sm text-gray-500">After saving, this account can be selected for check-in and room assignment.</p>
      <div className="mt-6 flex justify-end gap-2"><Button type="button" variant="outline" onClick={onClose}>Cancel</Button><Button disabled={processing}>Create guest account</Button></div>
    </form>
  </div>;
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return <label className="space-y-1 text-sm font-medium text-gray-700"><span>{label}</span>{children}{error && <span className="block text-red-600">{error}</span>}</label>;
}
