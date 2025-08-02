
import { useState } from "react";
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

// Mock data for rooms
const roomsData = [
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

const Rooms = () => {
  const [searchTerm, setSearchTerm] = useState("");
  const [viewType, setViewType] = useState<"grid" | "list">("grid");
  const [roomFilter, setRoomFilter] = useState<"all" | "available" | "occupied" | "cleaning">("all");
  
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

  const handleRoomClick = (room: typeof roomsData[0]) => {
    console.log("Room clicked:", room);
  };

  const handleToggleLock = (roomId: number) => {
    console.log("Toggle lock for room ID:", roomId);
    // In a real app, we would update the room's lock status
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
          <ActionButton
            icon={<RefreshCw size={16} />}
            variant="outline"
          >
            Refresh
          </ActionButton>
          <AddButton>
            Add Room
          </AddButton>
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
              <div className="p-4">
                <div className="flex justify-between items-center mb-2">
                  <h3 className="text-lg font-semibold">Room {room.roomNumber}</h3>
                  <StatusBadge 
                    status={room.status as "available" | "occupied" | "cleaning"} 
                  />
                </div>
                <p className="text-sm text-gray-500">{room.roomType} - Floor {room.floor}</p>
                <p className="text-sm font-medium mt-1">${room.price} / night</p>
                
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
                    Last cleaned: {new Date(room.lastCleaned).toLocaleDateString()}
                  </p>
                  <Button
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
                  </Button>
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
                      status={room.status as "available" | "occupied" | "cleaning"} 
                    />
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">{room.guestName || "—"}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm text-gray-900">${room.price}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <Button
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
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </AdminLayout>
  );
};

export default Rooms;
