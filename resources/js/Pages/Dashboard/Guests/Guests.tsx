
import { useState } from "react";
import { AdminLayout } from "@/components/layout/AdminLayout";
import { DataTable } from "@/components/DataTable";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { ActionButton } from "@/components/ActionButton";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { 
  Search, 
  Eye, 
  CheckCircle, 
  RefreshCw, 
  Plus, 
  Download,
  Filter
} from "lucide-react";
import { AddButton } from "@/components/Sidebar";

// Mock data for guests
const guestsData = [
  { 
    id: 1, 
    name: "John Smith", 
    email: "john.smith@example.com",
    phone: "+1-555-123-4567",
    checkInDate: "2025-04-23", 
    checkOutDate: "2025-04-25",
    idStatus: "verified", 
    roomNumber: "101" 
  },
  { 
    id: 2, 
    name: "Sarah Johnson", 
    email: "sarah.j@example.com",
    phone: "+1-555-987-6543", 
    checkInDate: "2025-04-23", 
    checkOutDate: "2025-04-27",
    idStatus: "pending", 
    roomNumber: "205" 
  },
  { 
    id: 3, 
    name: "Michael Brown", 
    email: "m.brown@example.com",
    phone: "+1-555-456-7890", 
    checkInDate: "2025-04-23", 
    checkOutDate: "2025-04-26",
    idStatus: "verified", 
    roomNumber: "310" 
  },
  { 
    id: 4, 
    name: "Emily Davis", 
    email: "emily.d@example.com",
    phone: "+1-555-222-3333", 
    checkInDate: "2025-04-23", 
    checkOutDate: "2025-04-24",
    idStatus: "pending", 
    roomNumber: "" 
  },
  { 
    id: 5, 
    name: "Robert Wilson", 
    email: "r.wilson@example.com",
    phone: "+1-555-444-5555", 
    checkInDate: "2025-04-24", 
    checkOutDate: "2025-04-28",
    idStatus: "rejected", 
    roomNumber: "" 
  },
  { 
    id: 6, 
    name: "Jessica Miller", 
    email: "j.miller@example.com",
    phone: "+1-555-666-7777", 
    checkInDate: "2025-04-24", 
    checkOutDate: "2025-04-26",
    idStatus: "verified", 
    roomNumber: "422" 
  },
];

const Guests = () => {
  const [searchTerm, setSearchTerm] = useState("");
  
  const filteredGuests = guestsData.filter(guest => 
    guest.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    guest.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
    guest.phone.includes(searchTerm)
  );
  
  const guestColumns = [
    {
      header: "Guest Name",
      accessor: "name" as const,
    },
    {
      header: "Email",
      accessor: "email" as const,
    },
    {
      header: "Check-in Date",
      accessor: "checkInDate" as const,
      cell: (item: typeof guestsData[0]) => {
        const date = new Date(item.checkInDate);
        return date.toLocaleDateString();
      },
    },
    {
      header: "Check-out Date",
      accessor: "checkOutDate" as const,
      cell: (item: typeof guestsData[0]) => {
        const date = new Date(item.checkOutDate);
        return date.toLocaleDateString();
      },
    },
    {
      header: "ID Status",
      accessor: "idStatus" as const,
      cell: (item: typeof guestsData[0]) => (
        <StatusBadge 
          status={item.idStatus as "verified" | "pending" | "rejected"} 
        />
      ),
    },
    {
      header: "Room",
      accessor: "roomNumber" as const,
      cell: (item: typeof guestsData[0]) => (
        item.roomNumber ? item.roomNumber : <span className="text-gray-400">Not Assigned</span>
      ),
    },
    {
      header: "Actions",
      accessor: "id" as const,
      cell: (item: typeof guestsData[0]) => (
        <div className="flex gap-2">
          <Button 
            variant="outline" 
            size="sm"
            className="flex items-center gap-1"
          >
            <Eye size={14} />
            <span className="hidden sm:inline">View</span>
          </Button>
          {item.idStatus === "pending" && (
            <Button 
              variant="outline" 
              size="sm"
              className="flex items-center gap-1 text-green-600 border-green-600 hover:bg-green-50"
            >
              <CheckCircle size={14} />
              <span className="hidden sm:inline">Verify</span>
            </Button>
          )}
        </div>
      ),
    }
  ];

  const handleGuestView = (guest: typeof guestsData[0]) => {
    console.log("View guest details:", guest);
    window.location.href = `/dashboard/guests/${guest.id}`;
  };

  return (
    <AdminLayout title="Guest Check-ins">
      <div className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-800">Guest Check-ins</h1>
        <div className="flex gap-3">
          <ActionButton
            icon={<RefreshCw size={16} />}
            variant="outline"
          >
            Refresh
          </ActionButton>
          <AddButton>
            New Check-in
          </AddButton>
        </div>
      </div>

      {/* Filters and Search */}
      <div className="bg-white p-4 rounded-lg shadow-md mb-6">
        <div className="flex flex-col md:flex-row gap-4 items-center">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" size={18} />
            <Input
              placeholder="Search guests..."
              className="pl-10"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <div className="flex gap-2">
            <Button variant="outline" className="flex items-center gap-2">
              <Filter size={16} />
              <span>Filter</span>
            </Button>
            <Button variant="outline" className="flex items-center gap-2">
              <Download size={16} />
              <span>Export</span>
            </Button>
          </div>
        </div>
      </div>

      {/* Guests Table */}
      <div className="bg-white rounded-lg shadow-md overflow-hidden">
        <DataTable
          data={filteredGuests}
          columns={guestColumns}
          onRowClick={handleGuestView}
        />
      </div>
    </AdminLayout>
  );
};

export default Guests;
