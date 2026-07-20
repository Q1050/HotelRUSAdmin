import { FormEvent, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export type GuestRequestThread = {
  id:number; guestName:string; type:string; details:string; priority:string; status:string; createdAt:string|null; unreadCount:number;
  messages:{id:number;sender:string;message:string;internal:boolean;attachmentUrl:string|null;createdAt:string|null}[];
  timeline:{label:string;occurredAt:string|null}[];
};

export function GuestRequestConversation({request}:{request:GuestRequestThread}) {
  const [tab,setTab]=useState<'messages'|'timeline'>('messages');
  const form=useForm<{message:string;internal:boolean;attachment:File|null}>({message:'',internal:false,attachment:null});
  const completion=useForm<{completion_message:string;completion_photo:File|null}>({completion_message:'',completion_photo:null});
  const send=(e:FormEvent)=>{e.preventDefault();form.post(route('dashboard.guest-requests.messages',request.id),{forceFormData:true,preserveScroll:true,onSuccess:()=>form.reset()})};
  const complete=(e:FormEvent)=>{e.preventDefault();completion.post(route('dashboard.guest-requests.complete',request.id),{forceFormData:true,preserveScroll:true,onSuccess:()=>completion.reset()})};
  const label=request.type.replaceAll('_',' ');
  const priorityStyle=request.priority==='high'||request.priority==='urgent'?'bg-red-100 text-red-700':request.priority==='low'?'bg-slate-100 text-slate-600':'bg-amber-100 text-amber-800';

  return <div className="mt-5 rounded-lg border border-blue-100 bg-blue-50/40 p-4">
    <div className="flex flex-wrap items-start justify-between gap-3">
      <div><h3 className="font-semibold">Guest app request</h3><p className="text-xs text-gray-500">{request.guestName}{request.createdAt&&` · ${new Date(request.createdAt).toLocaleString()}`}</p></div>
      <div className="flex items-center gap-2"><span className={`rounded-full px-2.5 py-1 text-xs font-medium capitalize ${priorityStyle}`}>{request.priority} priority</span>{request.unreadCount>0&&<Button size="sm" variant="outline" onClick={()=>router.patch(route('dashboard.guest-requests.read',request.id),{},{preserveScroll:true})}>{request.unreadCount} unread</Button>}</div>
    </div>
    <div className="mt-3 rounded-lg border border-blue-100 bg-white p-3"><p className="text-xs font-semibold uppercase tracking-wide text-blue-700">{label}</p><p className="mt-1 whitespace-pre-wrap text-sm text-gray-800">{request.details}</p></div>
    <div className="mt-3 flex gap-2"><Button size="sm" variant={tab==='messages'?'default':'outline'} onClick={()=>setTab('messages')}>Messages</Button><Button size="sm" variant={tab==='timeline'?'default':'outline'} onClick={()=>setTab('timeline')}>Timeline</Button></div>
    {tab==='messages'?<>
      <div className="mt-3 max-h-52 space-y-2 overflow-y-auto">{request.messages.map(m=><div key={m.id} className={`rounded-lg p-3 text-sm ${m.internal?'bg-amber-100 text-amber-900':m.sender==='Guest'?'bg-white':'bg-blue-100 text-blue-900'}`}><div className="flex justify-between gap-2 text-xs font-medium"><span>{m.internal?'Internal note':m.sender}</span><span className="font-normal opacity-70">{m.createdAt?new Date(m.createdAt).toLocaleString():''}</span></div><p className="mt-1 whitespace-pre-wrap">{m.message}</p>{m.attachmentUrl&&<a href={m.attachmentUrl} target="_blank" rel="noreferrer" className="mt-2 inline-block text-xs font-medium underline">View attachment</a>}</div>)}{request.messages.length===0&&<p className="py-5 text-center text-sm text-gray-500">No follow-up messages yet.</p>}</div>
      <form onSubmit={send} className="mt-3 space-y-2"><textarea required maxLength={2000} className="min-h-20 w-full rounded-md border bg-white p-2 text-sm" placeholder="Reply to the guest…" value={form.data.message} onChange={e=>form.setData('message',e.target.value)}/><div className="flex flex-wrap items-center justify-between gap-2"><div className="flex items-center gap-3"><label className="text-xs"><input className="mr-1" type="checkbox" checked={form.data.internal} onChange={e=>form.setData('internal',e.target.checked)}/>Internal note</label><input className="max-w-48 text-xs" type="file" accept="image/jpeg,image/png,image/webp" onChange={e=>form.setData('attachment',e.target.files?.[0]??null)}/></div><Button size="sm" disabled={form.processing}>Send</Button></div></form>
      {!['completed','confirmed','cancelled'].includes(request.status)&&<form onSubmit={complete} className="mt-4 border-t pt-3"><p className="text-xs font-medium text-gray-600">Share completion with guest</p><textarea required className="mt-2 min-h-16 w-full rounded-md border bg-white p-2 text-sm" value={completion.data.completion_message} onChange={e=>completion.setData('completion_message',e.target.value)} placeholder="What was completed?"/><div className="mt-2 flex items-center justify-between gap-2"><input className="max-w-48 text-xs" type="file" accept="image/jpeg,image/png,image/webp" onChange={e=>completion.setData('completion_photo',e.target.files?.[0]??null)}/><Button size="sm" variant="outline" disabled={completion.processing}>Complete &amp; notify</Button></div></form>}
    </>:<div className="mt-3 space-y-3 border-l-2 border-blue-200 pl-4">{request.timeline.map((item,index)=><div key={index}><p className="text-sm font-medium">{item.label}</p><p className="text-xs text-gray-500">{item.occurredAt?new Date(item.occurredAt).toLocaleString():''}</p></div>)}{request.timeline.length===0&&<p className="text-sm text-gray-500">No timeline events yet.</p>}</div>}
  </div>;
}
