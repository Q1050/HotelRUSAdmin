
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Lock } from "lucide-react";
import { useNavigate } from "react-router-dom";
export const Login = () => {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  const handleLogin = (e: React.FormEvent) => {
    e.preventDefault();
     const navigate = useNavigate();
    console.log("Login attempt:", { email, password });
    // In a real app, we would handle authentication here
    //navigate("/dashboard");
    //window.location.href = "/dashboard";
  };

  return (
    <div className="min-h-screen flex flex-col md:flex-row">
      {/* Left Side - Brand */}
      <div className="hidden md:flex md:w-1/2 bg-hotel-navy p-8 flex-col justify-between">
        <div className="flex items-center gap-2">
          <Lock className="text-hotel-gold" size={32} />
          <h1 className="text-2xl font-bold text-white">HotelKey</h1>
        </div>
        
        <div>
          <h2 className="text-4xl font-bold text-white mb-4">Hotel Self Check-In System</h2>
          <p className="text-white/80 text-lg">
            Efficiently manage guest check-ins, room assignments, and ID verifications
          </p>
        </div>
        
        <div className="text-white/60 text-sm">
          © 2025 HotelKey. All rights reserved.
        </div>
      </div>
      
      {/* Right Side - Login Form */}
      <div className="flex-1 flex items-center justify-center p-8 md:p-12">
        <div className="w-full max-w-md">
          <div className="md:hidden flex items-center gap-2 justify-center mb-8">
            <Lock className="text-hotel-navy" size={32} />
            <h1 className="text-2xl font-bold text-hotel-navy">HotelKey</h1>
          </div>
          
          <div className="text-center mb-8">
            <h2 className="text-2xl font-bold text-gray-800 mb-2">Welcome Back </h2>
            <p className="text-gray-600">Sign in to your account</p>
          </div>
          
          <form className="space-y-6">
            <div className="space-y-2">
              <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                Email
              </label>
              <Input
                id="email"
                type="email"
                placeholder="Enter your email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="w-full"
              />
            </div>
            
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                  Password
                </label>
                <a href="#" className="text-sm text-hotel-navy hover:underline">
                  Forgot password?
                </a>
              </div>
              <Input
                id="password"
                type="password"
                placeholder="Enter your password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="w-full"
              />
            </div>
            
            <Button type="submit" className="w-full bg-hotel-navy hover:bg-hotel-navy/90" onClick={handleLogin}>
              Sign in
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
};

export default Login;
