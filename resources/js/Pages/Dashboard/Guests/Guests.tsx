
import { useState } from "react";
import { AdminLayout } from "@/components/layout/AdminLayout";
import { DataTable } from "@/components/DataTable";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { ActionButton } from "@/components/ActionButton";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { router } from "@inertiajs/react";
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
import { GuestFormModal } from "@/components/GuestFormModal";
import { GuestCheckinModal } from "@/components/GuestCheckinModal";

type GuestStatus = "verified" | "pending" | "rejected";

interface GuestRow {
  id: number;
  name: string;
  email: string | null;
  phone: string | null;
  checkInDate: string | null;
  checkOutDate: string | null;
  idStatus: GuestStatus;
  roomNumber: string | null;
  createdAt: string | null;
}

interface GuestsProps {
  guests: GuestRow[];
}

const Guests = ({ guests }: GuestsProps) => {
  const [searchTerm, setSearchTerm] = useState("");
  const [statusFilter, setStatusFilter] = useState<"all" | GuestStatus>("all");
  const [showCreate, setShowCreate] = useState(false);
  const [showCheckin, setShowCheckin] = useState(false);
  
  const filteredGuests = guests.filter((guest) =>
    guest.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    (guest.email ?? "").toLowerCase().includes(searchTerm.toLowerCase()) ||
    (guest.phone ?? "").includes(searchTerm) &&
    (statusFilter === "all" || guest.idStatus === statusFilter)
  );
  
  const guestColumns = [
    {
      header: "Guest Name",
      accessor: "name" as const,
    },
    {
      header: "Email",
      accessor: "email" as const,
      cell: (item: GuestRow) => item.email || <span className="text-gray-400">N/A</span>,
    },
    {
      header: "Check-in Date",
      accessor: "checkInDate" as const,
      cell: (item: GuestRow) => {
        if (!item.checkInDate) {
          return <span className="text-gray-400">N/A</span>;
        }

        const date = new Date(item.checkInDate);
        return date.toLocaleDateString();
      },
    },
    {
      header: "Check-out Date",
      accessor: "checkOutDate" as const,
      cell: (item: GuestRow) => {
        if (!item.checkOutDate) {
          return <span className="text-gray-400">N/A</span>;
        }

        const date = new Date(item.checkOutDate);
        return date.toLocaleDateString();
      },
    },
    {
      header: "ID Status",
      accessor: "idStatus" as const,
      cell: (item: GuestRow) => (
        <StatusBadge 
          status={item.idStatus}
        />
      ),
    },
    {
      header: "Room",
      accessor: "roomNumber" as const,
      cell: (item: GuestRow) => (
        item.roomNumber ? item.roomNumber : <span className="text-gray-400">Not Assigned</span>
      ),
    },
    {
      header: "Actions",
      accessor: "id" as const,
      cell: (item: GuestRow) => (
        <div className="flex gap-2">
          <Button 
            variant="outline" 
            size="sm"
            className="flex items-center gap-1"
            onClick={(e) => {
              e.stopPropagation();
              router.visit(route('dashboard.guests.show', item.id));
            }}
          >
            <Eye size={14} />
            <span className="hidden sm:inline">View</span>
          </Button>
          {item.idStatus === "pending" && (
            <Button 
              variant="outline" 
              size="sm"
              className="flex items-center gap-1 text-green-600 border-green-600 hover:bg-green-50"
              onClick={(e) => {
                e.stopPropagation();
                router.patch(route('dashboard.guests.verify-id', item.id));
              }}
            >
              <CheckCircle size={14} />
              <span className="hidden sm:inline">Verify</span>
            </Button>
          )}
        </div>
      ),
    }
  ];

  const handleGuestView = (guest: GuestRow) => {
    router.visit(route('dashboard.guests.show', guest.id));
  };

  return (
    <AdminLayout title="Guest Check-ins">
      <div className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-800">Guest Check-ins</h1>
        <div className="flex gap-3">
          <ActionButton onClick={() => router.reload({ only: ['guests'] })}
            icon={<RefreshCw size={16} />}
            variant="outline"
          >
            Refresh
          </ActionButton>
          <Button variant="outline" onClick={() => setShowCreate(true)}>
            <Plus size={16} className="mr-2" />Guest Account
          </Button>
          <AddButton onClick={() => setShowCheckin(true)}>
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
            <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value as typeof statusFilter)} className="rounded-md border border-gray-300 px-3 py-2 text-sm">
              <option value="all">All statuses</option><option value="pending">Pending</option><option value="verified">Verified</option><option value="rejected">Rejected</option>
            </select>
            <Button variant="outline" className="flex items-center gap-2" onClick={() => setStatusFilter(statusFilter === 'all' ? 'pending' : 'all')}>
              <Filter size={16} />
              <span>Filter</span>
            </Button>
            <Button variant="outline" className="flex items-center gap-2" onClick={() => {
              const rows = [['Name','Email','Phone','ID status','Room'], ...filteredGuests.map(g => [g.name, g.email ?? '', g.phone ?? '', g.idStatus, g.roomNumber ?? ''])];
              const csv = rows.map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\n');
              const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' })); link.download = 'guests.csv'; link.click(); URL.revokeObjectURL(link.href);
            }}>
              <Download size={16} />
              <span>Export</span>
            </Button>
          </div>
        </div>
      </div>
      <GuestFormModal open={showCreate} onClose={() => setShowCreate(false)} />
      <GuestCheckinModal open={showCheckin} onClose={() => setShowCheckin(false)} onCreateAccount={() => setShowCreate(true)} />

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
