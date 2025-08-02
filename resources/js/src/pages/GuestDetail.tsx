
import { useParams } from "react-router-dom";
import { AdminLayout } from "@/components/layout/AdminLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/ui/StatusBadge";
import { Check, ArrowLeft, Key } from "lucide-react";

// Mock data for a specific guest
const guestData = {
  id: 2,
  name: "Sarah Johnson",
  email: "sarah.j@example.com",
  phone: "+1-555-987-6543",
  checkInDate: "2025-04-23",
  checkOutDate: "2025-04-27",
  idStatus: "pending",
  roomNumber: "205",
  address: "123 Main Street, Anytown, CA 94321",
  idType: "Passport",
  idNumber: "AX1234567",
  notes: "Guest requested extra pillows.",
  paymentStatus: "Paid",
  bookingReference: "BK1234567",
};

// Mock data for available rooms
const availableRooms = ["101", "205", "302", "310", "405"];

const GuestDetail = () => {
  const { id } = useParams<{ id: string }>();
  // In a real app, we would fetch the guest data based on the ID
  const guest = guestData;

  const handleBack = () => {
    window.location.href = "/guests";
  };

  const handleVerifyId = () => {
    console.log("Verifying ID for guest:", guest.id);
    // In a real app, we would update the guest's ID status
  };

  const handleAssignRoom = () => {
    console.log("Assigning room for guest:", guest.id);
    // In a real app, we would update the guest's room assignment
  };

  const handleGenerateKey = () => {
    console.log("Generating key for guest:", guest.id);
    // In a real app, we would trigger the key generation process
  };

  return (
    <AdminLayout>
      <div className="mb-6 flex items-center gap-4">
        <Button variant="ghost" onClick={handleBack} className="p-2">
          <ArrowLeft size={20} />
        </Button>
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Guest Details</h1>
          <p className="text-gray-600">View and manage guest information</p>
        </div>
      </div>

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
                <p className="text-gray-900">{guest.email}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <p className="text-gray-900">{guest.phone}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <p className="text-gray-900">{guest.address}</p>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-md p-6">
            <h2 className="text-xl font-semibold mb-4">Booking Information</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Check-in Date</label>
                <p className="text-gray-900">{new Date(guest.checkInDate).toLocaleDateString()}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Check-out Date</label>
                <p className="text-gray-900">{new Date(guest.checkOutDate).toLocaleDateString()}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Booking Reference</label>
                <p className="text-gray-900">{guest.bookingReference}</p>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                <p className="text-gray-900">{guest.paymentStatus}</p>
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
                  <p className="text-gray-900">{guest.idType}</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">ID Number</label>
                  <p className="text-gray-900">{guest.idNumber}</p>
                </div>
              </div>

              {guest.idStatus === "pending" && (
                <Button 
                  onClick={handleVerifyId} 
                  className="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700"
                >
                  <Check size={18} />
                  <span>Verify ID</span>
                </Button>
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
                defaultValue={guest.roomNumber || ""}
              >
                <option value="" disabled>Select a room</option>
                {availableRooms.map((room) => (
                  <option key={room} value={room}>{room}</option>
                ))}
              </select>
            </div>
            
            <div className="space-y-3">
              <Button 
                onClick={handleAssignRoom} 
                className="w-full"
              >
                Assign Room
              </Button>
              
              <Button 
                onClick={handleGenerateKey} 
                variant="outline"
                className="w-full flex items-center justify-center gap-2"
              >
                <Key size={18} />
                <span>Generate Key</span>
              </Button>
            </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
};

export default GuestDetail;
