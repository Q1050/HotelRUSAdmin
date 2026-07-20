
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Lock } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
//import { useNavigate } from "react-router-dom";
import axios from "axios";
export const Login = () => {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });
   // const navigate = useNavigate();
    
    const submit: FormEventHandler = async(e) => {
        e.preventDefault();
       // window.location.href = '/dashboard/'
       // navigate("/dashboard");
        post('/authenticate/login', {
          onError: () => {
            alert('Login failed');
          }
        });
        /*
       const response = await axios.post('/authenticate/login', {
          email: data.email,
          password: data.password,
        }, {
                headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
      },
          withCredentials: true // if you're using Sanctum
        });    
        
        response.status === 200 ? window.location.href = '/dashboard/' : alert('Login failed');
        */
        
      };

  return (
    <div className="min-h-screen flex flex-col md:flex-row">
      <Head title="Welcome Back Admin: Sign in to your account" />
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
            <h2 className="text-2xl font-bold text-gray-800 mb-2">Welcome Back Admin</h2>
            <p className="text-gray-600">Sign in to your account</p>
          </div>
          
          <form onSubmit={submit} className="space-y-6">
            <div className="space-y-2">
              <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                Email
              </label>
              <Input
                id="email"
                type="email"
                placeholder="Enter your email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                required
                className="w-full"
              />
            </div>
            
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                  Password
                </label>
                <Link href={route('password.request')} className="text-sm text-hotel-navy hover:underline">
                  Forgot password?
                </Link>
              </div>
              <Input
                id="password"
                type="password"
                placeholder="Enter your password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                required
                className="w-full"
              />
            </div>
            
            <Button type="submit" className="w-full bg-hotel-navy hover:bg-hotel-navy/90">
              Sign in
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
};

export default Login;
