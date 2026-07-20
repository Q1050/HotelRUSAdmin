import { FormEvent, useEffect, useState } from "react";
import { router, useForm } from "@inertiajs/react";
import { AdminLayout } from "@/components/layout/AdminLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { AlertTriangle, Battery, CloudCog, Copy, Download, KeyRound, Plus, Printer, QrCode, RefreshCw, Search, Smartphone } from "lucide-react";
import QRCode from "qrcode";

type Device = {
    id: number;
    name: string;
    provider: string;
    externalId: string;
    status: string;
    batteryLevel: number;
    lastSeenAt: string | null;
    hardwareModel: string | null;
    firmwareVersion: string | null;
    capabilities: string[];
    syncStatus: string;
    syncError: string | null;
    lastSyncedAt: string | null;
    room: { id: number; number: string; accessMarker: string } | null;
};
type RoomOption = { id: number; number: string };
type Provider = { id:number;key:string;name:string;driver:string;baseUrl:string|null;active:boolean;connectionStatus:string;lastError:string|null;lastTestedAt:string|null;hasCredentials:boolean;webhookUrl:string };
type Discovered = { provider:string;external_id:string;name:string;hardware_model:string|null;firmware_version:string|null;battery_level:number;status:string;capabilities:string[] };
type Attempt = { id:number;deviceName:string|null;operation:string;status:string;error:string|null;createdAt:string|null };

export default function Locks({
    devices,
    rooms,
    providers,
    discovered,
    recentAttempts,
}: {
    devices: Device[];
    rooms: RoomOption[];
    providers: Provider[];
    discovered: Discovered[];
    recentAttempts: Attempt[];
}) {
    const [search, setSearch] = useState("");
    const [open, setOpen] = useState(false);
    const [assigning, setAssigning] = useState<Device | null>(null);
    const [roomId, setRoomId] = useState("");
    const [providerOpen,setProviderOpen]=useState(false);
    const [inventoryOpen,setInventoryOpen]=useState(false);
    const [provisioning,setProvisioning]=useState<Device|null>(null);
    const [qrImage,setQrImage]=useState("");
    const form = useForm({ name: "", external_id: "", provider: "simulator", hardware_model:"", firmware_version:"" });
    const providerForm=useForm({id:"",name:"",key:"",driver:"generic_rest",base_url:"",token:"",webhook_secret:"",active:true as boolean});
    const saveProvider=(e:FormEvent)=>{e.preventDefault();providerForm.post(route('dashboard.locks.providers.store'),{preserveScroll:true,onSuccess:()=>providerForm.reset('token','webhook_secret')})};
    const importDiscovered=()=>router.post(route('dashboard.locks.import'),{devices:discovered},{preserveScroll:true,onSuccess:()=>setInventoryOpen(false)});
    useEffect(()=>{if(!provisioning?.room?.accessMarker){setQrImage("");return;}QRCode.toDataURL(provisioning.room.accessMarker,{width:420,margin:2,errorCorrectionLevel:"H"}).then(setQrImage);},[provisioning]);
    const rotateMarker=()=>{if(!provisioning?.room)return;const reason=window.prompt("Why are you regenerating this room marker? Existing QR codes and NFC tags will stop working.");if(reason&&reason.length>=10)router.patch(route('dashboard.locks.marker.rotate',provisioning.room.id),{reason},{preserveScroll:true,onSuccess:()=>setProvisioning(null)});};
    const printMarker=()=>{if(!provisioning?.room||!qrImage)return;const popup=window.open("","_blank","width=600,height=750");if(!popup)return;popup.document.write(`<!doctype html><title>Room ${provisioning.room.number} access marker</title><body style="font-family:system-ui;text-align:center;padding:48px"><h1>Room ${provisioning.room.number}</h1><p>Scan in the hotel guest app to access your assigned room.</p><img src="${qrImage}" width="420" height="420"/><p style="font-size:12px;color:#555">Marker ${provisioning.room.accessMarker}</p><script>onload=()=>print()<\/script></body>`);popup.document.close();};
    const filtered = devices.filter((d) =>
        `${d.name} ${d.externalId} ${d.room?.number ?? ""} ${d.status}`
            .toLowerCase()
            .includes(search.toLowerCase()),
    );
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route("dashboard.locks.store"), {
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };
    const assign = () =>
        assigning &&
        router.patch(
            route("dashboard.locks.assign", assigning.id),
            { room_id: roomId },
            {
                onSuccess: () => {
                    setAssigning(null);
                    setRoomId("");
                },
            },
        );
    return (
        <AdminLayout title="Lock Management">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold">Smart lock inventory</h1>
                    <p className="text-gray-600">
                        Register locks in bulk, then assign them to hotel rooms.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" onClick={()=>setProviderOpen(true)}><CloudCog className="mr-2" size={16}/>Providers</Button>
                    {discovered.length>0&&<Button variant="outline" onClick={()=>setInventoryOpen(true)}><Download className="mr-2" size={16}/>Discovered ({discovered.length})</Button>}
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.reload({ only: ["devices", "rooms"] })
                        }
                    >
                        <RefreshCw className="mr-2" size={16} />
                        Refresh
                    </Button>
                    <Button onClick={() => setOpen(true)}>
                        <Plus className="mr-2" size={16} />
                        Add lock
                    </Button>
                </div>
            </div>
            <div className="mb-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Stat label="Total locks" value={devices.length} />
                <Stat
                    label="Assigned"
                    value={devices.filter((d) => d.room).length}
                />
                <Stat
                    label="Unassigned inventory"
                    value={devices.filter((d) => !d.room).length}
                />
                <Stat label="Needs attention" value={devices.filter(d=>d.status!=='online'||d.batteryLevel<25||d.syncStatus==='failed').length}/>
            </div>
            <div className="mb-4 rounded-lg bg-white p-4 shadow">
                <div className="relative">
                    <Search
                        className="absolute left-3 top-3 text-gray-400"
                        size={18}
                    />
                    <Input
                        className="pl-10"
                        placeholder="Search lock, device ID, room, or status…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                {filtered.map((device) => (
                    <div
                        key={device.id}
                        className="rounded-lg bg-white p-6 pb-8 shadow"
                    >
                        <div className="flex items-start justify-between">
                            <div className="flex gap-3 ">
                                <div className="rounded-lg bg-hotel-navy/10 p-4 pb-3">
                                    <KeyRound className="text-hotel-navy" />
                                </div>
                                <div>
                                    <h2 className="font-semibold">
                                        {device.name}
                                    </h2>
                                    <p className="text-xs text-gray-500">
                                        {device.externalId} · {device.provider}
                                    </p>
                                    <p className="mt-1 text-xs text-gray-500">{device.hardwareModel??'Model unknown'} · Firmware {device.firmwareVersion??'unknown'}</p>
                                </div>
                            </div>
                            <span
                                className={`rounded-full px-2 py-1 text-xs ${device.status === "online" ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700"}`}
                            >
                                {device.status}
                            </span>
                        </div>
                        <div className="mt-4 grid grid-cols-2 gap-3 rounded-md bg-gray-50 p-3 text-sm">
                            <span>
                                <Battery className="mr-1 inline" size={15} />
                                Battery {device.batteryLevel}%
                            </span>
                            <span>
                                Room:{" "}
                                <strong>
                                    {device.room?.number ?? "Unassigned"}
                                </strong>
                            </span>
                            <span className="col-span-2 text-xs text-gray-500">
                                Last seen:{" "}
                                {device.lastSeenAt
                                    ? new Date(
                                          device.lastSeenAt,
                                      ).toLocaleString()
                                    : "Never"}
                            </span>
                            <span className={`col-span-2 text-xs ${device.syncStatus==='failed'?'text-red-600':'text-gray-500'}`}>Sync: {device.syncStatus}{device.lastSyncedAt?` · ${new Date(device.lastSyncedAt).toLocaleString()}`:''}{device.syncError?` · ${device.syncError}`:''}</span>
                            {device.capabilities.length>0&&<span className="col-span-2 flex flex-wrap gap-1">{device.capabilities.map(item=><span key={item} className="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] text-blue-700">{item}</span>)}</span>}
                        </div>
                        <div className="mt-4 flex justify-end gap-2">
                            {device.room ? (
                                <>
                                    <Button variant="outline" onClick={()=>setProvisioning(device)}><QrCode className="mr-2" size={15}/>Room marker</Button>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                route(
                                                    "dashboard.locks.sync",
                                                    device.id,
                                                ),
                                            )
                                        }
                                    >
                                        Sync
                                    </Button>
                                    {(device.status!=='online'||device.syncStatus==='failed')&&<Button variant="outline" className="text-amber-700" onClick={()=>{const reason=window.prompt('Emergency access reason (minimum 10 characters):');if(reason&&reason.length>=10)router.post(route('dashboard.locks.emergency',device.id),{reason},{preserveScroll:true})}}><AlertTriangle className="mr-2" size={15}/>Emergency</Button>}
                                    <Button
                                        variant="outline"
                                        className="text-red-600"
                                        onClick={() =>
                                            window.confirm(
                                                "Return this lock to unassigned inventory?",
                                            ) &&
                                            router.patch(
                                                route(
                                                    "dashboard.locks.unassign",
                                                    device.id,
                                                ),
                                            )
                                        }
                                    >
                                        Unassign
                                    </Button>
                                </>
                            ) : (
                                <Button onClick={() => setAssigning(device)}>
                                    Assign to room
                                </Button>
                            )}
                        </div>
                    </div>
                ))}
                {filtered.length === 0 && (
                    <div className="col-span-full rounded-lg bg-white p-10 text-center text-gray-500">
                        No locks found.
                    </div>
                )}
            </div>
            {open && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    onMouseDown={() => setOpen(false)}
                >
                    <form
                        onSubmit={submit}
                        onMouseDown={(e) => e.stopPropagation()}
                        className="w-full max-w-md rounded-lg bg-white p-6"
                    >
                        <h2 className="text-xl font-semibold">
                            Add lock to inventory
                        </h2>
                        <div className="mt-4 space-y-4">
                            <Field label="Lock name" error={form.errors.name}>
                                <Input
                                    placeholder="West Wing Lock 01"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData("name", e.target.value)
                                    }
                                    required
                                />
                            </Field>
                            <Field
                                label="Hardware / serial ID"
                                error={form.errors.external_id}
                            >
                                <Input
                                    placeholder="LOCK-0001"
                                    value={form.data.external_id}
                                    onChange={(e) =>
                                        form.setData(
                                            "external_id",
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </Field>
                            <Field label="Provider">
                                <select
                                    className="w-full rounded-md border p-2"
                                    value={form.data.provider}
                                    onChange={(e) =>
                                        form.setData("provider", e.target.value)
                                    }
                                >
                                    <option value="simulator">Simulator</option>
                                    {providers.filter(item=>item.active).map(item=><option key={item.id} value={item.key}>{item.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Hardware model"><Input value={form.data.hardware_model} onChange={e=>form.setData('hardware_model',e.target.value)} placeholder="Optional"/></Field>
                            <Field label="Firmware version"><Input value={form.data.firmware_version} onChange={e=>form.setData('firmware_version',e.target.value)} placeholder="Optional"/></Field>
                        </div>
                        <div className="mt-6 flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button disabled={form.processing}>Add lock</Button>
                        </div>
                    </form>
                </div>
            )}
            {assigning && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    onMouseDown={() => setAssigning(null)}
                >
                    <div
                        onMouseDown={(e) => e.stopPropagation()}
                        className="w-full max-w-md rounded-lg bg-white p-6"
                    >
                        <h2 className="text-xl font-semibold">
                            Assign {assigning.name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Only rooms without another paired lock are shown.
                        </p>
                        <select
                            className="mt-4 w-full rounded-md border p-2"
                            value={roomId}
                            onChange={(e) => setRoomId(e.target.value)}
                        >
                            <option value="">Select a room</option>
                            {rooms.map((room) => (
                                <option key={room.id} value={room.id}>
                                    Room {room.number}
                                </option>
                            ))}
                        </select>
                        <div className="mt-6 flex justify-end gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setAssigning(null)}
                            >
                                Cancel
                            </Button>
                            <Button disabled={!roomId} onClick={assign}>
                                Assign lock
                            </Button>
                        </div>
                    </div>
                </div>
            )}
            {providerOpen&&<div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-6 backdrop-blur-sm" onMouseDown={()=>setProviderOpen(false)}><div className="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl" onMouseDown={e=>e.stopPropagation()}><div className="flex items-start justify-between"><div><h2 className="text-xl font-semibold">Lock providers</h2><p className="text-sm text-gray-500">Credentials are encrypted and are never returned to the browser.</p></div><Button variant="outline" onClick={()=>setProviderOpen(false)}>Close</Button></div><div className="mt-5 space-y-3">{providers.map(provider=><div key={provider.id} className="rounded-lg border p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><strong>{provider.name}</strong><p className="text-xs text-gray-500">{provider.driver} · {provider.baseUrl??'No endpoint'}</p><p className="mt-1 text-xs text-gray-500">Webhook: {provider.webhookUrl}</p></div><span className={`rounded-full px-2 py-1 text-xs ${provider.connectionStatus==='connected'?'bg-green-100 text-green-700':provider.connectionStatus==='failed'?'bg-red-100 text-red-700':'bg-gray-100 text-gray-600'}`}>{provider.connectionStatus}</span></div>{provider.lastError&&<p className="mt-2 text-xs text-red-600">{provider.lastError}</p>}<div className="mt-3 flex justify-end gap-2"><Button size="sm" variant="outline" onClick={()=>router.post(route('dashboard.locks.providers.test',provider.id))}>Test connection</Button><Button size="sm" onClick={()=>router.post(route('dashboard.locks.providers.discover',provider.id),{},{onSuccess:()=>setInventoryOpen(true)})}>Discover devices</Button></div></div>)}</div><form onSubmit={saveProvider} className="mt-6 border-t pt-5"><h3 className="font-semibold">Add provider</h3><div className="mt-4 grid gap-4 md:grid-cols-2"><Field label="Display name" error={providerForm.errors.name}><Input value={providerForm.data.name} onChange={e=>providerForm.setData('name',e.target.value)} required/></Field><Field label="Provider key" error={providerForm.errors.key}><Input value={providerForm.data.key} onChange={e=>providerForm.setData('key',e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g,''))} placeholder="vendor_name" required/></Field><Field label="Driver"><select className="w-full rounded-md border p-2" value={providerForm.data.driver} onChange={e=>providerForm.setData('driver',e.target.value)}><option value="generic_rest">Generic REST</option><option value="simulator">Simulator</option></select></Field><Field label="Base URL" error={providerForm.errors.base_url}><Input type="url" value={providerForm.data.base_url} onChange={e=>providerForm.setData('base_url',e.target.value)} placeholder="https://locks.example.com/api/"/></Field><Field label="API token"><Input type="password" value={providerForm.data.token} onChange={e=>providerForm.setData('token',e.target.value)} autoComplete="new-password"/></Field><Field label="Webhook secret"><Input type="password" minLength={16} value={providerForm.data.webhook_secret} onChange={e=>providerForm.setData('webhook_secret',e.target.value)} placeholder="Generated automatically if blank"/></Field></div><label className="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" checked={providerForm.data.active} onChange={e=>providerForm.setData('active',e.target.checked)}/>Active provider</label><div className="mt-5 flex justify-end"><Button disabled={providerForm.processing}>Save provider</Button></div></form></div></div>}
            {provisioning?.room&&<div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" onMouseDown={()=>setProvisioning(null)}><div className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl" onMouseDown={e=>e.stopPropagation()}><div className="flex items-start justify-between gap-4"><div><h2 className="text-xl font-semibold">Room {provisioning.room.number} access marker</h2><p className="mt-1 text-sm text-gray-500">Place the QR code or an NFC tag inside the room entrance area. It only works for the guest currently assigned to this room.</p></div><Button variant="outline" onClick={()=>setProvisioning(null)}>Close</Button></div><div className="mt-5 grid gap-5 sm:grid-cols-[220px_1fr]"><div className="rounded-xl border bg-white p-3">{qrImage?<img src={qrImage} alt={`Room ${provisioning.room.number} QR access marker`} className="w-full"/>:<div className="aspect-square animate-pulse rounded bg-gray-100"/>}</div><div className="space-y-3"><div className="rounded-lg bg-blue-50 p-4 text-sm text-blue-900"><QrCode className="mb-2" size={20}/><strong>QR setup</strong><p className="mt-1 text-blue-700">Print and mount this marker. The app reads the marker, then the server confirms the guest, stay, room, device, ID and payment.</p></div><div className="rounded-lg bg-violet-50 p-4 text-sm text-violet-900"><Smartphone className="mb-2" size={20}/><strong>NFC setup</strong><p className="mt-1 text-violet-700">Write the marker value as plain text to a passive NFC tag.</p></div></div></div><div className="mt-5 rounded-lg bg-gray-50 p-3"><p className="text-xs font-medium uppercase tracking-wide text-gray-500">NFC marker value</p><div className="mt-1 flex items-center gap-2"><code className="min-w-0 flex-1 break-all text-xs">{provisioning.room.accessMarker}</code><Button size="sm" variant="outline" onClick={()=>navigator.clipboard.writeText(provisioning.room!.accessMarker)}><Copy size={14}/></Button></div></div><div className="mt-6 flex flex-wrap justify-between gap-3 border-t pt-5"><Button variant="outline" className="text-red-600" onClick={rotateMarker}><RefreshCw className="mr-2" size={15}/>Regenerate marker</Button><div className="flex gap-2"><a href={qrImage} download={`room-${provisioning.room.number}-access-qr.png`}><Button type="button" variant="outline" disabled={!qrImage}><Download className="mr-2" size={15}/>Download</Button></a><Button onClick={printMarker} disabled={!qrImage}><Printer className="mr-2" size={15}/>Print marker</Button></div></div></div></div>}
            {inventoryOpen&&<div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-6 backdrop-blur-sm" onMouseDown={()=>setInventoryOpen(false)}><div className="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl" onMouseDown={e=>e.stopPropagation()}><div className="flex justify-between"><div><h2 className="text-xl font-semibold">Discovered devices</h2><p className="text-sm text-gray-500">Review provider inventory before importing.</p></div><Button variant="outline" onClick={()=>setInventoryOpen(false)}>Close</Button></div><div className="mt-5 space-y-3">{discovered.map(device=><div key={`${device.provider}-${device.external_id}`} className="rounded-lg border p-4"><div className="flex justify-between gap-3"><div><strong>{device.name}</strong><p className="text-xs text-gray-500">{device.external_id} · {device.hardware_model??'Unknown model'} · Firmware {device.firmware_version??'unknown'}</p></div><span className="text-sm">{device.battery_level}%</span></div></div>)}{discovered.length===0&&<p className="rounded-lg bg-gray-50 p-6 text-center text-gray-500">No devices were returned by the provider.</p>}</div><div className="mt-6 flex justify-end"><Button disabled={discovered.length===0} onClick={importDiscovered}>Import {discovered.length} devices</Button></div></div></div>}
            {recentAttempts.some(item=>item.status==='failed')&&<div className="mt-6 rounded-xl bg-white p-5 shadow"><h2 className="font-semibold">Failed synchronization attempts</h2><div className="mt-3 space-y-2">{recentAttempts.filter(item=>item.status==='failed').map(item=><div key={item.id} className="flex items-center justify-between gap-3 rounded-lg bg-red-50 p-3 text-sm"><span><strong>{item.deviceName}</strong> · {item.operation}<small className="block text-red-600">{item.error}</small></span><Button size="sm" variant="outline" onClick={()=>router.post(route('dashboard.locks.retry',item.id))}>Retry</Button></div>)}</div></div>}
        </AdminLayout>
    );
}
function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg bg-white p-4 shadow">
            <p className="text-sm text-gray-500">{label}</p>
            <p className="text-2xl font-semibold">{value}</p>
        </div>
    );
}
function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block space-y-1 text-sm font-medium">
            <span>{label}</span>
            {children}
            {error && <span className="block text-red-600">{error}</span>}
        </label>
    );
}
