
import { AdminLayout } from "@/components/layout/AdminLayout";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { Link, router, useForm, usePage } from "@inertiajs/react";
import { Check, ArrowLeft, Key, X, Smartphone, TabletSmartphone, MonitorSmartphone, Wifi, BellRing, Link2, ShieldAlert, FileJson, Trash2 } from "lucide-react";
import { useState } from "react";

type GuestStatus = "verified" | "pending" | "rejected";

interface AvailableRoom {
  id: number;
  label: string;
}

interface GuestDetailModel {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  checkInDate: string | null;
  checkOutDate: string | null;
  idStatus: GuestStatus;
  roomNumber: string | null;
  address: string | null;
  idType: string | null;
  idNumber: string | null;
  notes: string | null;
  paymentStatus: string | null;
  bookingReference: string | null;
  createdAt: string | null;
  checkinId: number | null;
  isActive: boolean;
  hasLockDevice: boolean;
  accessSuspendedAt: string | null;
  accessSuspensionReason: string | null;
  doNotRentAt: string | null;
  doNotRentReason: string | null;
}

interface GuestDetailProps {
  guest: GuestDetailModel;
  devices: GuestDeviceModel[];
  privacyRequests: PrivacyRequestModel[];
  availableRooms: AvailableRoom[];
}

interface PrivacyRequestModel { id:number;type:'export'|'deletion';status:string;guestReason:string|null;reviewNotes:string|null;createdAt:string|null;reviewedAt:string|null }

interface LinkedGuestModel {
  id: number | null;
  name: string;
  email: string | null;
  lastSeenAt: string | null;
  revokedAt: string | null;
}

interface GuestDeviceModel {
  id: number;
  deviceId: string;
  name: string | null;
  platform: string | null;
  ipAddress: string | null;
  firstSeenAt: string | null;
  lastSeenAt: string | null;
  revokedAt: string | null;
  hasPush: boolean;
  linkedGuests: LinkedGuestModel[];
}

const GuestDetail = ({ guest, devices, privacyRequests, availableRooms }: GuestDetailProps) => {
  const page = usePage().props as unknown as { flash: { success?: string; generatedKey?: string }; auth:{user:{permissions?:string[]}} };
  const flash = page.flash;
  const canForce = page.auth.user.permissions?.includes('stays.force_departure') ?? false;
  const [departureOpen,setDepartureOpen]=useState(false);
  const { data, setData, processing, errors } = useForm<{ room_id: string }>({
    room_id: "",
  });
  const departure=useForm({departure_type:'early',reason:'',financial_resolution:'pending',refund_amount:'0',security_involved:false as boolean,do_not_rent:false as boolean,notes:''});

  const handleVerifyId = () => {
    router.patch(route('dashboard.guests.verify-id', guest.id));
  };

  const handleAssignRoom = () => {
    router.patch(route('dashboard.guests.assign-room', guest.id), {
      room_id: data.room_id,
    });
  };

  const handleGenerateKey = (type: 'mobile' | 'rfid') => {
    if (guest.checkinId) router.post(route('dashboard.checkins.key', guest.checkinId), { type });
  };

  const handleCheckout = () => {
    setDepartureOpen(true);
  };
  const submitDeparture=()=>{if(!guest.checkinId)return;departure.patch(route('dashboard.checkins.checkout',guest.checkinId),{onSuccess:()=>setDepartureOpen(false),preserveScroll:true})};
  const suspendAccess=()=>{const reason=window.prompt('Why is room access being suspended?');if(reason?.trim()&&guest.checkinId)router.patch(route('dashboard.checkins.suspend-access',guest.checkinId),{reason:reason.trim()},{preserveScroll:true})};
  const restoreAccess=()=>{const reason=window.prompt('Reason for restoring access:');if(reason?.trim()&&guest.checkinId)router.patch(route('dashboard.checkins.restore-access',guest.checkinId),{reason:reason.trim()},{preserveScroll:true})};
  const reviewPrivacy=(id:number,decision:'approved'|'rejected')=>{const notes=window.prompt(`${decision==='approved'?'Approve and anonymize this account':'Reject this request'} — enter required review notes:`);if(notes?.trim())router.patch(route('dashboard.privacy-requests.review',id),{decision,notes:notes.trim()},{preserveScroll:true});};

  return (
    <AdminLayout>
      {flash?.success && <div className="mb-4 rounded-md bg-green-50 p-3 text-green-800">{flash.success}</div>}
      {flash?.generatedKey && <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 p-4"><strong>New room key:</strong> <code className="ml-2 text-lg">{flash.generatedKey}</code><p className="mt-1 text-xs text-amber-800">Copy it now; it is only shown once.</p></div>}
      <div className="mb-6 flex items-center gap-4">
        <Button variant="ghost" asChild className="p-2">
          <Link href={route('dashboard.guests.index')}>
          <ArrowLeft size={20} />
          </Link>
        </Button>
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Guest Details</h1>
          <p className="text-gray-600">View and manage guest information</p>
        </div>
      </div>
      {guest.doNotRentAt&&<div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900"><div><strong>Do not rent</strong><p className="mt-1 text-sm">{guest.doNotRentReason||'This profile was restricted by management.'}</p></div>{canForce&&<Button variant="outline" onClick={()=>{const reason=window.prompt('Manager reason for releasing this restriction:');if(reason?.trim())router.patch(route('dashboard.guests.release-restriction',guest.id),{reason:reason.trim()},{preserveScroll:true})}}>Release restriction</Button>}</div>}
      {guest.accessSuspendedAt&&<div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900"><div><strong>Room access suspended</strong><p className="mt-1 text-sm">{guest.accessSuspensionReason}</p></div>{canForce&&<Button variant="outline" onClick={restoreAccess}>Restore access</Button>}</div>}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Guest Information */}
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-white rounded-lg shadow-md p-6">
            <h2 className="text-xl font-semibold mb-4">Personal Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <p className="text-gray-900">{guest.name}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <p className="text-gray-900">{guest.email || "Not provided"}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <p className="text-gray-900">{guest.phone || "Not provided"}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <p className="text-gray-900">{guest.address || "Not provided"}</p>
              </div>
            </div>
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-xl font-semibold text-gray-900">Guest devices</h2>
                <p className="mt-1 text-sm text-gray-500">Mobile sessions, network details, and device reuse across guest accounts.</p>
              </div>
              <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{devices.length} registered</span>
            </div>

            {devices.length === 0 ? (
              <div className="rounded-xl border border-dashed border-gray-300 p-8 text-center">
                <Smartphone className="mx-auto mb-3 text-gray-400" size={30}/>
                <p className="font-medium text-gray-700">No mobile devices registered</p>
                <p className="mt-1 text-sm text-gray-500">Devices appear after the guest signs into the mobile app.</p>
              </div>
            ) : (
              <div className="space-y-4">
                {devices.map(device => {
                  const active = !device.revokedAt;
                  const DeviceIcon = device.platform === 'ios' ? Smartphone : device.platform === 'android' ? TabletSmartphone : MonitorSmartphone;
                  return <div key={device.id} className="rounded-xl border border-gray-200 p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="flex min-w-0 items-start gap-3">
                        <div className={`rounded-xl p-2.5 ${active ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-500'}`}><DeviceIcon size={22}/></div>
                        <div className="min-w-0">
                          <div className="flex flex-wrap items-center gap-2">
                            <p className="font-semibold text-gray-900">{device.name || (device.platform === 'ios' ? 'Apple device' : device.platform === 'android' ? 'Android device' : 'Guest device')}</p>
                            <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>{active ? 'Active' : 'Revoked'}</span>
                            {device.hasPush && <span title="Push notifications enabled" className="text-violet-600"><BellRing size={15}/></span>}
                          </div>
                          <p className="mt-1 break-all font-mono text-xs text-gray-500">{device.deviceId}</p>
                        </div>
                      </div>
                      <span className="rounded-md bg-gray-100 px-2 py-1 text-xs font-medium uppercase text-gray-600">{device.platform || 'unknown'}</span>
                    </div>

                    <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                      <div className="rounded-lg bg-gray-50 p-3"><p className="text-xs font-medium text-gray-500">IP address</p><p className="mt-1 flex items-center gap-1.5 font-medium text-gray-800"><Wifi size={14}/>{device.ipAddress || 'Unavailable'}</p></div>
                      <div className="rounded-lg bg-gray-50 p-3"><p className="text-xs font-medium text-gray-500">First seen</p><p className="mt-1 font-medium text-gray-800">{device.firstSeenAt ? new Date(device.firstSeenAt).toLocaleString() : 'Unavailable'}</p></div>
                      <div className="rounded-lg bg-gray-50 p-3"><p className="text-xs font-medium text-gray-500">Last active</p><p className="mt-1 font-medium text-gray-800">{device.lastSeenAt ? new Date(device.lastSeenAt).toLocaleString() : 'Unavailable'}</p></div>
                    </div>

                    {device.linkedGuests.length > 0 && <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                      <div className="flex items-center gap-2 text-sm font-semibold text-amber-900"><Link2 size={16}/>Also used by {device.linkedGuests.length} other guest account{device.linkedGuests.length === 1 ? '' : 's'}</div>
                      <div className="mt-2 space-y-2">{device.linkedGuests.map(linked => <div key={`${device.deviceId}-${linked.id}`} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div>{linked.id ? <Link className="font-medium text-amber-950 underline decoration-amber-300" href={route('dashboard.guests.show', linked.id)}>{linked.name}</Link> : <span>{linked.name}</span>}<span className="ml-2 text-amber-800">{linked.email || 'No email'}</span></div>
                        <span className="text-xs text-amber-700">{linked.revokedAt ? 'Revoked' : 'Active'}{linked.lastSeenAt ? ` · ${new Date(linked.lastSeenAt).toLocaleString()}` : ''}</span>
                      </div>)}</div>
                    </div>}
                  </div>;
                })}
              </div>
            )}
          </div>

          <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div className="mb-5 flex items-start gap-3"><div className="rounded-xl bg-violet-50 p-2.5 text-violet-700"><ShieldAlert size={21}/></div><div><h2 className="text-xl font-semibold">Privacy requests</h2><p className="mt-1 text-sm text-gray-500">Guest data exports and reviewed account-deletion requests.</p></div></div>
            {privacyRequests.length===0?<p className="rounded-xl border border-dashed p-6 text-center text-sm text-gray-500">No privacy requests from this guest.</p>:<div className="space-y-3">{privacyRequests.map(item=><div key={item.id} className="rounded-xl border border-gray-200 p-4"><div className="flex flex-wrap items-start justify-between gap-3"><div className="flex items-start gap-3">{item.type==='deletion'?<Trash2 className="mt-0.5 text-red-600" size={19}/>:<FileJson className="mt-0.5 text-blue-600" size={19}/>}<div><p className="font-semibold capitalize">{item.type} request</p><p className="text-xs text-gray-500">{item.createdAt?new Date(item.createdAt).toLocaleString():'Unknown date'}</p>{item.guestReason&&<p className="mt-2 text-sm text-gray-700">{item.guestReason}</p>}{item.reviewNotes&&<p className="mt-2 rounded-lg bg-gray-50 p-2 text-xs text-gray-600">Review: {item.reviewNotes}</p>}</div></div><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${item.status==='pending'?'bg-amber-100 text-amber-800':item.status==='completed'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-600'}`}>{item.status}</span></div>{item.type==='deletion'&&item.status==='pending'&&<div className="mt-4 flex justify-end gap-2"><Button variant="outline" onClick={()=>reviewPrivacy(item.id,'rejected')}>Reject</Button><Button variant="destructive" onClick={()=>reviewPrivacy(item.id,'approved')}>Approve &amp; anonymize</Button></div>}</div>)}</div>}
          </div>

          <div className="bg-white rounded-lg shadow-md p-6">
            <h2 className="text-xl font-semibold mb-4">Booking Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Check-in Date</label>
                <p className="text-gray-900">{guest.checkInDate ? new Date(guest.checkInDate).toLocaleDateString() : "N/A"}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Check-out Date</label>
                <p className="text-gray-900">{guest.checkOutDate ? new Date(guest.checkOutDate).toLocaleDateString() : "N/A"}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Booking Reference</label>
                <p className="text-gray-900">{guest.bookingReference}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                <p className="text-gray-900">{guest.paymentStatus || "Unknown"}</p>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-md p-6">
            <h2 className="text-xl font-semibold mb-4">Notes</h2>
            <p className="text-gray-900">{guest.notes || "No notes available."}</p>
          </div>
        </div>

        {/* ID Verification and Room Assignment */}
        <div className="space-y-6">
          {/* ID Verification */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-semibold">ID Verification</h2>
              <StatusBadge status={guest.idStatus as "verified" | "pending" | "rejected"} />
            </div>
            
            <div className="mb-4">
              <div className="aspect-[5/3] bg-gray-100 rounded-lg flex items-center justify-center mb-4">
                <div className="text-gray-400 text-center p-4">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-12 w-12 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                  </svg>
                  <p>ID Document Preview</p>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 mb-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">ID Type</label>
                  <p className="text-gray-900">{guest.idType || "N/A"}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                  <p className="text-gray-900">{guest.idNumber || "N/A"}</p>
                </div>
              </div>

              {guest.idStatus === "pending" && (
                <div className="grid grid-cols-2 gap-2"><Button
                  onClick={handleVerifyId} 
                  className="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700"
                >
                  <Check size={18} />
                  <span>Verify ID</span>
                </Button>
                <Button variant="outline" onClick={() => router.patch(route('dashboard.guests.reject-id', guest.id))} className="text-red-600">
                  <X size={18} /><span>Reject</span>
                </Button></div>
              )}
            </div>
          </div>

          {/* Room Assignment */}
          <div className="bg-white rounded-lg shadow-md p-6">
            <h2 className="text-xl font-semibold mb-4">Room Assignment</h2>
            
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">Current Room</label>
              <p className="text-gray-900">{guest.roomNumber || "No room assigned"}</p>
            </div>
            
            <div className="mb-6">
              <label htmlFor="room" className="block text-sm font-medium text-gray-700 mb-1">
                Assign New Room
              </label>
              <select
                id="room"
                className="w-full border-gray-300 rounded-md shadow-sm focus:border-hotel-navy focus:ring focus:ring-hotel-navy focus:ring-opacity-50"
                  value={data.room_id}
                  onChange={(e) => setData('room_id', e.target.value)}
              >
                <option value="" disabled>Select a room</option>
                {availableRooms.map((room) => (
                  <option key={room.id} value={room.id}>{room.label}</option>
                ))}
              </select>
                {errors.room_id && <p className="mt-2 text-sm text-red-600">{errors.room_id}</p>}
            </div>
            
            <div className="space-y-3">
              <Button 
                onClick={handleAssignRoom} 
                  disabled={processing || !data.room_id}
                className="w-full"
              >
                Assign Room
              </Button>
              
              <Button 
                onClick={() => handleGenerateKey('mobile')}
                disabled={!guest.isActive || !guest.roomNumber || !guest.hasLockDevice || !!guest.accessSuspendedAt}
                variant="outline"
                className="w-full flex items-center justify-center gap-2"
              >
                <Key size={18} />
                <span>Issue Mobile Key</span>
              </Button>
              <Button onClick={() => handleGenerateKey('rfid')} disabled={!guest.isActive || !guest.roomNumber || !guest.hasLockDevice || !!guest.accessSuspendedAt} variant="outline" className="w-full">Issue RFID Credential</Button>
              {guest.isActive && guest.roomNumber && !guest.hasLockDevice && <p className="text-center text-xs text-amber-700">Pair a smart lock with this room before issuing a mobile key.</p>}
              {guest.isActive&&guest.roomNumber&&canForce&&!guest.accessSuspendedAt&&<Button onClick={suspendAccess} variant="outline" className="w-full border-amber-300 text-amber-800"><ShieldAlert className="mr-2" size={17}/>Emergency suspend access</Button>}
              {guest.isActive && guest.roomNumber && <Button onClick={handleCheckout} variant="destructive" className="w-full">Check Out</Button>}
            </div>
          </div>
        </div>
      </div>
      {departureOpen&&<div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onMouseDown={()=>setDepartureOpen(false)}><div className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl" onMouseDown={e=>e.stopPropagation()}><h2 className="text-xl font-semibold">End stay</h2><p className="mt-1 text-sm text-gray-500">Choose how this departure should be recorded. Room credentials will be revoked immediately.</p><div className="mt-5 space-y-4"><label className="block text-sm font-medium">Departure type<select className="mt-1 w-full rounded-md border p-2" value={departure.data.departure_type} onChange={e=>departure.setData('departure_type',e.target.value)}><option value="normal">Normal checkout</option><option value="early">Early voluntary checkout</option>{canForce&&<option value="forced">Forced checkout / eviction</option>}</select></label><label className="block text-sm font-medium">Reason<textarea className="mt-1 min-h-24 w-full rounded-md border p-2" required={departure.data.departure_type==='forced'} value={departure.data.reason} onChange={e=>departure.setData('reason',e.target.value)} placeholder={departure.data.departure_type==='forced'?'Required for forced checkout':'Optional departure reason'}/></label><div className="grid gap-4 sm:grid-cols-2"><label className="block text-sm font-medium">Financial handling<select className="mt-1 w-full rounded-md border p-2" value={departure.data.financial_resolution} onChange={e=>departure.setData('financial_resolution',e.target.value)}><option value="pending">Pending review</option><option value="no_refund">No refund</option><option value="partial_refund">Partial refund</option><option value="full_refund">Full refund</option><option value="charge_balance">Charge remaining balance</option></select></label><label className="block text-sm font-medium">Refund amount<input className="mt-1 w-full rounded-md border p-2" type="number" min="0" step="0.01" value={departure.data.refund_amount} onChange={e=>departure.setData('refund_amount',e.target.value)}/></label></div>{departure.data.departure_type==='forced'&&<div className="space-y-2 rounded-lg border border-red-200 bg-red-50 p-4"><label className="flex gap-2 text-sm"><input type="checkbox" checked={departure.data.security_involved} onChange={e=>departure.setData('security_involved',e.target.checked)}/>Security was involved</label><label className="flex gap-2 text-sm"><input type="checkbox" checked={departure.data.do_not_rent} onChange={e=>departure.setData('do_not_rent',e.target.checked)}/>Add guest to do-not-rent list</label><p className="text-xs text-red-700">Forced checkout also revokes every active mobile session for this guest.</p></div>}{Object.values(departure.errors).map((error,i)=><p key={i} className="text-sm text-red-600">{error}</p>)}</div><div className="mt-6 flex justify-end gap-2 border-t pt-5"><Button variant="outline" onClick={()=>setDepartureOpen(false)}>Cancel</Button><Button variant="destructive" disabled={departure.processing||departure.data.departure_type==='forced'&&!departure.data.reason.trim()} onClick={submitDeparture}>{departure.data.departure_type==='forced'?'Complete forced checkout':'Complete checkout'}</Button></div></div></div>}
    </AdminLayout>
  );
};

export default GuestDetail;
