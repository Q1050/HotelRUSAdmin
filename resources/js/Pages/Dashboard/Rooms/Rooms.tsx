
import { useState } from "react";
import { Link, router, useForm, usePage } from "@inertiajs/react";
import { AdminLayout } from "@/components/layout/AdminLayout";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { ActionButton } from "@/components/ActionButton";
import { AddButton } from "@/components/Sidebar";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { 
  Search, 
  RefreshCw,
  Lock,
  Unlock,
  Filter
} from "lucide-react";

enum typesofRooms{standard,deluxe,suite,penthouse }
interface roomTypes{
  type:typesofRooms
}
interface roomModel {
  id:string,
  roomType: roomTypes
  floor: number,
  status: roomStatus
}
const fallbackRooms = [
  {
    id: 1,
    roomNumber: "101",
    roomType: "Standard",
    floor: 1,
    status: "available",
    lockStatus: "locked",
    price: 120,
    lastCleaned: "2025-04-22",
    guestName: null
  },
  {
    id: 2,
    roomNumber: "102",
    roomType: "Standard",
    floor: 1,
    status: "occupied",
    lockStatus: "unlocked",
    price: 120,
    lastCleaned: "2025-04-22",
    guestName: "John Smith"
  },
  {
    id: 3,
    roomNumber: "103",
    roomType: "Standard",
    floor: 1,
    status: "cleaning",
    lockStatus: "unlocked",
    price: 120,
    lastCleaned: "2025-04-23",
    guestName: null
  },
  {
    id: 4,
    roomNumber: "201",
    roomType: "Deluxe",
    floor: 2,
    status: "available",
    lockStatus: "locked",
    price: 180,
    lastCleaned: "2025-04-22",
    guestName: null
  },
  {
    id: 5,
    roomNumber: "202",
    roomType: "Deluxe",
    floor: 2,
    status: "occupied",
    lockStatus: "unlocked",
    price: 180,
    lastCleaned: "2025-04-21",
    guestName: "Sarah Johnson"
  },
  {
    id: 6,
    roomNumber: "301",
    roomType: "Suite",
    floor: 3,
    status: "available",
    lockStatus: "locked",
    price: 250,
    lastCleaned: "2025-04-23",
    guestName: null
  },
  {
    id: 7,
    roomNumber: "302",
    roomType: "Suite",
    floor: 3,
    status: "occupied",
    lockStatus: "unlocked",
    price: 250,
    lastCleaned: "2025-04-20",
    guestName: "Michael Brown"
  },
  {
    id: 8,
    roomNumber: "401",
    roomType: "Penthouse",
    floor: 4,
    status: "available",
    lockStatus: "locked",
    price: 350,
    lastCleaned: "2025-04-22",
    guestName: null
  },
];

enum roomStatus {
  available,
  occupied,
  cleaning
}
enum lockStatus {
  locked,
  unlocked}

interface RoomHistoryEvent { id: number; type: string; description: string; guestName: string | null; actorName: string | null; occurredAt: string | null }
interface LockDeviceView { id: number; name: string; provider: string; status: string; batteryLevel: number; lastSeenAt: string | null }
type DisplayRoomStatus = "available" | "occupied" | "cleaning" | "pending_housekeeping" | "awaiting_inspection" | "maintenance_required";
interface RoomView { id: number; roomNumber: string; roomType: string; floor: number; status: string; housekeepingStatus: string | null; housekeepingAssignee: string | null; maintenanceStatus: string | null; lockStatus: string; price: number; lastCleaned: string | null; guestName: string | null; history: RoomHistoryEvent[]; device: LockDeviceView | null }
interface RoomsProps { rooms: RoomView[] }
const Rooms = ({ rooms }: RoomsProps) => {
  const currentUser = usePage().props.auth.user;
  const canManage = currentUser.role === 'super_admin' || currentUser.role === 'manager' || currentUser.role === 'housekeeping';
  const roomsData = rooms ?? [];
  const [searchTerm, setSearchTerm] = useState("");
  const [viewType, setViewType] = useState<"grid" | "list">("grid");
  const [roomFilter, setRoomFilter] = useState<"all" | "available" | "occupied" | "cleaning">("all");
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const form = useForm({ number: '', type: 'Standard', floor: '1', status: 'available', price: '', last_cleaned_at: '' });

  const displayStatus = (room: RoomView): DisplayRoomStatus => {
    if (room.status !== 'cleaning') return room.status as 'available' | 'occupied';
    if (room.maintenanceStatus && !['inspected', 'cancelled'].includes(room.maintenanceStatus)) return 'maintenance_required';
    if (room.housekeepingStatus === 'pending') return 'pending_housekeeping';
    if (room.housekeepingStatus === 'completed') return 'awaiting_inspection';
    return 'cleaning';
  };
  
  // Apply filters
  const filteredRooms = roomsData.filter(room => {
    const matchesSearch = 
      room.roomNumber.toLowerCase().includes(searchTerm.toLowerCase()) ||
      room.roomType.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (room.guestName && room.guestName.toLowerCase().includes(searchTerm.toLowerCase()));
    
    const matchesFilter = 
      roomFilter === "all" || 
      room.status === roomFilter;
    
    return matchesSearch && matchesFilter;
  });

  // Get stats
  const totalRooms = roomsData.length;
  const availableRooms = roomsData.filter(room => room.status === "available").length;
  const occupiedRooms = roomsData.filter(room => room.status === "occupied").length;

  const handleRoomClick = (room: RoomView) => {
    if (!canManage) return;
    setEditingId(room.id);
    form.setData({ number: room.roomNumber, type: room.roomType, floor: String(room.floor), status: room.status, price: String(room.price), last_cleaned_at: room.lastCleaned ?? '' });
    setShowForm(true);
  };

  const handleToggleLock = (roomId: number) => {
    router.patch(route('dashboard.rooms.lock', roomId), {}, { preserveScroll: true });
  };

  const openCreate = () => { setEditingId(null); form.reset(); form.setData({ number: '', type: 'Standard', floor: '1', status: 'available', price: '', last_cleaned_at: '' }); setShowForm(true); };
  const saveRoom = (event: React.FormEvent) => {
    event.preventDefault();
    const options = { preserveScroll: true, onSuccess: () => setShowForm(false) };
    editingId ? form.patch(route('dashboard.rooms.update', editingId), options) : form.post(route('dashboard.rooms.store'), options);
  };

  const getRoomStatusColor = (status: string) => {
    switch (status) {
      case "available": return "bg-green-100 border-green-200";
      case "occupied": return "bg-red-50 border-red-100";
      case "cleaning": return "bg-yellow-50 border-yellow-100";
      default: return "bg-gray-50 border-gray-100";
    }
  };

  return (
    <AdminLayout title="Room Management">
      <div className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-800">Room Management</h1>
        <div className="flex gap-3">
          <ActionButton onClick={() => router.reload({ only: ['rooms'] })}
            icon={<RefreshCw size={16} />}
            variant="outline"
          >
            Refresh
          </ActionButton>
          {canManage && <AddButton onClick={openCreate}>
            Add Room
          </AddButton>}
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div className="bg-white p-4 rounded-lg shadow-md">
          <h3 className="text-sm font-medium text-gray-500">Total Rooms</h3>
          <p className="text-2xl font-semibold text-hotel-navy">{totalRooms}</p>
        </div>
        <div className="bg-white p-4 rounded-lg shadow-md">
          <h3 className="text-sm font-medium text-gray-500">Available Rooms</h3>
          <p className="text-2xl font-semibold text-green-600">{availableRooms}</p>
        </div>
        <div className="bg-white p-4 rounded-lg shadow-md">
          <h3 className="text-sm font-medium text-gray-500">Occupied Rooms</h3>
          <p className="text-2xl font-semibold text-red-600">{occupiedRooms}</p>
        </div>
      </div>

      {/* Filters and Search */}
      <div className="bg-white p-4 rounded-lg shadow-md mb-6">
        <div className="flex flex-col md:flex-row gap-4 items-center">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
            <Input
              placeholder="Search rooms..."
              className="pl-10"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <div className="flex gap-2">
            <div className="flex rounded-md overflow-hidden">
              <Button 
                variant={roomFilter === "all" ? "default" : "outline"} 
                className="rounded-r-none"
                onClick={() => setRoomFilter("all")}
              >
                All
              </Button>
              <Button 
                variant={roomFilter === "available" ? "default" : "outline"} 
                className="rounded-none border-l-0 border-r-0"
                onClick={() => setRoomFilter("available")}
              >
                Available
              </Button>
              <Button 
                variant={roomFilter === "occupied" ? "default" : "outline"} 
                className="rounded-l-none"
                onClick={() => setRoomFilter("occupied")}
              >
                Occupied
              </Button>
              <Button variant={roomFilter === "cleaning" ? "default" : "outline"} className="rounded-l-none border-l-0" onClick={() => setRoomFilter("cleaning")}>Cleaning</Button>
            </div>
            <div className="flex rounded-md overflow-hidden">
              <Button 
                variant={viewType === "grid" ? "default" : "outline"} 
                className="rounded-r-none px-3"
                onClick={() => setViewType("grid")}
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
              </Button>
              <Button 
                variant={viewType === "list" ? "default" : "outline"} 
                className="rounded-l-none px-3"
                onClick={() => setViewType("list")}
              >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
              </Button>
            </div>
          </div>
        </div>
      </div>

      {/* Rooms Grid or List */}
      {viewType === "grid" ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {filteredRooms.map((room) => (
            <div 
              key={room.id}
              className={`${getRoomStatusColor(room.status)} border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow cursor-pointer`}
              onClick={() => handleRoomClick(room)}
            >
              <div className="p-6">
                <div className="flex justify-between items-center mb-2">
                  <h3 className="text-lg font-semibold">Room {room.roomNumber}</h3>
                  <StatusBadge 
                    status={displayStatus(room)}
                  />
                </div>
                <p className="text-sm text-gray-500">{room.roomType} - Floor {room.floor}</p>
                <p className="text-sm font-medium mt-1">${room.price} / night</p>
                {room.status === 'cleaning' && <p className="mt-1 text-xs text-gray-600">Housekeeper: {room.housekeepingAssignee ?? 'Not assigned'}</p>}
                
                <div className="mt-3 pt-3 border-t border-gray-100">
                  {room.guestName ? (
                    <p className="text-sm">
                      <span className="text-gray-500">Guest:</span> {room.guestName}
                    </p>
                  ) : (
                    <p className="text-sm text-gray-500">No guest assigned</p>
                  )}
                </div>
                
                <div className="mt-3 flex justify-between items-center">
                  <p className="text-xs text-gray-500">
                    Last cleaned: {room.lastCleaned ? new Date(room.lastCleaned).toLocaleDateString() : 'Not recorded'}
                  </p>
                  {canManage && <Button
                    variant="ghost"
                    size="sm"
                    className="p-1"
                    onClick={(e) => {
                      e.stopPropagation();
                      handleToggleLock(room.id);
                    }}
                  >
                    {room.lockStatus === "locked" ? (
                      <Lock size={16} className="text-gray-600" />
                    ) : (
                      <Unlock size={16} className="text-gray-600" />
                    )}
                  </Button>}
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="bg-white rounded-lg shadow-md overflow-hidden">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Room</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lock</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredRooms.map((room) => (
                <tr 
                  key={room.id}
                  className="hover:bg-gray-50 cursor-pointer"
                  onClick={() => handleRoomClick(room)}
                >
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">{room.roomNumber}</div>
                    <div className="text-xs text-gray-500">Floor {room.floor}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{room.roomType}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <StatusBadge 
                      status={displayStatus(room)}
                    />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{room.guestName || "—"}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">${room.price}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {canManage && <Button
                      variant="ghost"
                      size="sm"
                      className="p-1"
                      onClick={(e) => {
                        e.stopPropagation();
                        handleToggleLock(room.id);
                      }}
                    >
                      {room.lockStatus === "locked" ? (
                        <Lock size={16} className="text-gray-600" />
                      ) : (
                        <Unlock size={16} className="text-gray-600" />
                      )}
                    </Button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {showForm && <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onMouseDown={() => setShowForm(false)}>
        <form onSubmit={saveRoom} onMouseDown={(e) => e.stopPropagation()} className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
          <h2 className="mb-4 text-xl font-semibold">{editingId ? 'Edit room' : 'Add room'}</h2>
          <div className="grid gap-4 md:grid-cols-2">
            <RoomField label="Room number" error={form.errors.number}><Input value={form.data.number} onChange={(e) => form.setData('number', e.target.value)} required /></RoomField>
            <RoomField label="Type" error={form.errors.type}><select className="w-full rounded-md border border-gray-300 px-3 py-2" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} required><option value="Standard">Standard</option><option value="Deluxe">Deluxe</option><option value="Suite">Suite</option><option value="Penthouse">Penthouse</option></select></RoomField>
            <RoomField label="Floor" error={form.errors.floor}><Input type="number" min="0" value={form.data.floor} onChange={(e) => form.setData('floor', e.target.value)} required /></RoomField>
            <RoomField label="Nightly price" error={form.errors.price}><Input type="number" min="0" step="0.01" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} required /></RoomField>
            <RoomField label="Status" error={form.errors.status}><select className="w-full rounded-md border border-gray-300 px-3 py-2" value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}><option value="available">Available</option><option value="occupied">Occupied</option><option value="cleaning">Cleaning</option></select></RoomField>
            <RoomField label="Last cleaned" error={form.errors.last_cleaned_at}><Input type="date" value={form.data.last_cleaned_at} onChange={(e) => form.setData('last_cleaned_at', e.target.value)} /></RoomField>
          </div>
          {editingId && (() => { const room = roomsData.find(item => item.id === editingId); return <div className="mt-6 border-t pt-5"><div className="flex items-center justify-between gap-3"><h3 className="font-semibold">Recent room history</h3>{room && <Link href={route('dashboard.rooms.history', room.id)} className="text-sm font-medium text-hotel-navy hover:underline">View all history</Link>}</div><p className="mb-3 text-sm text-gray-500">The five most recent assignments, access grants, cleaning, status, and lock events.</p><div className="space-y-3">{room?.history.slice(0, 5).map(event => <HistoryEntry key={event.id} event={event}/>)}{(room?.history.length ?? 0) === 0 && <p className="rounded-md bg-gray-50 p-3 text-sm text-gray-500">No history has been recorded for this room yet.</p>}</div></div>; })()}
          {editingId && <div className="mt-6 border-t pt-5"><h3 className="font-semibold">Smart door lock</h3>{(() => { const device=roomsData.find(room=>room.id===editingId)?.device; return device ? <div className="mt-3 rounded-md bg-gray-50 p-4"><div className="flex items-center justify-between"><div><strong>{device.name}</strong><p className="text-xs text-gray-500">Provider: {device.provider} · Last seen: {device.lastSeenAt ? new Date(device.lastSeenAt).toLocaleString() : 'Never'}</p></div><span className={`rounded-full px-2 py-1 text-xs ${device.status==='online'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}`}>{device.status}</span></div><div className="mt-3 flex items-center justify-between"><span className="text-sm">Battery: {device.batteryLevel}%</span><div className="flex gap-2"><Button type="button" size="sm" variant="outline" onClick={()=>router.post(route('dashboard.locks.sync',device.id),{}, {preserveScroll:true})}>Sync</Button><Button type="button" size="sm" onClick={()=>{const reason=window.prompt('Reason for remotely unlocking this door:');if(reason&&reason.trim().length>=5)router.post(route('dashboard.locks.unlock',device.id),{reason},{preserveScroll:true})}}>Remote Unlock</Button></div></div></div> : <div className="mt-3 rounded-md border border-dashed p-4 text-center"><p className="text-sm text-gray-600">No smart lock is paired with this room.</p><Button type="button" className="mt-3" size="sm" onClick={()=>router.post(route('dashboard.locks.pair',editingId),{}, {preserveScroll:true})}>Pair Simulated Lock</Button></div>; })()}</div>}
          <div className="mt-6 flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setShowForm(false)}>Cancel</Button><Button disabled={form.processing}>{editingId ? 'Save changes' : 'Add room'}</Button></div>
        </form>
      </div>}
    </AdminLayout>
  );
};

export default Rooms;

function RoomField({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return <label className="space-y-1 text-sm font-medium text-gray-700"><span>{label}</span>{children}{error && <span className="block text-red-600">{error}</span>}</label>;
}

function HistoryEntry({ event }: { event: RoomHistoryEvent }) {
  return <div className="border-l-2 border-hotel-navy pl-3"><div className="flex flex-col justify-between gap-1 sm:flex-row sm:gap-3"><strong className="text-sm">{event.description}</strong><span className="whitespace-nowrap text-xs text-gray-500">{event.occurredAt ? new Date(event.occurredAt).toLocaleString() : ''}</span></div><p className="text-xs text-gray-500">{event.guestName && `Guest: ${event.guestName}`}{event.guestName && event.actorName && ' · '}{event.actorName && `Staff: ${event.actorName}`}</p></div>;
}
