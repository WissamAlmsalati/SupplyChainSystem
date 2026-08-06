import React, { useState, useEffect, useRef } from 'react';
import {
  Bot,
  Sparkles,
  Send,
  RefreshCw,
  AlertCircle,
  CheckCircle,
  ArrowRight,
  ShieldAlert,
  Clock,
  X
} from 'lucide-react';
import { AIRecommendation } from '../types';
import { usePagination } from '../hooks/usePagination';
import Pagination from './Pagination';

interface AIAssistantViewProps {
  recommendations: AIRecommendation[];
  onTriggerScan: () => Promise<void>;
  onUpdateRecStatus: (id: string, status: string) => Promise<void>;
  highlightRecId?: string;
}

export default function AIAssistantView({
  recommendations,
  onTriggerScan,
  onUpdateRecStatus,
  highlightRecId
}: AIAssistantViewProps) {
  const newRecommendations = recommendations.filter(rec => rec.status === 'new');
  const { page, setPage, paginatedItems, totalPages, pageSize, totalItems } = usePagination(newRecommendations, 10);

  const [messages, setMessages] = useState<Array<{ sender: 'user' | 'assistant'; text: string; time: string }>>([
    {
      sender: 'assistant',
      text: "### TAQA AI Operations Officer Online\n\nI have fully grounded access to our current Employee Master, active rig rosters, document validity status, and credential expiration calendars.\n\nAsk me questions like:\n- *\"Who has expired safety certificates?\"*\n- *\"Do we have any scheduling conflicts on our active oilfield assignments?\"*\n- *\"Summarize our overall crew compliance rate.\"*\n\nHow can I optimize our crew coordination today?",
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    }
  ]);

  const [inputMessage, setInputMessage] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [isScanning, setIsScanning] = useState(false);
  const chatEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleScanClick = async () => {
    setIsScanning(true);
    await onTriggerScan();
    setIsScanning(false);
  };

  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputMessage.trim() || isSending) return;

    const userText = inputMessage;
    setInputMessage('');
    setIsSending(true);

    const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setMessages(prev => [...prev, { sender: 'user', text: userText, time: timeStr }]);

    try {
      const res = await fetch('/api/ai/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: userText })
      });
      const data = await res.json();
      
      setMessages(prev => [...prev, {
        sender: 'assistant',
        text: data.text || "An unexpected error occurred during processing.",
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      }]);
    } catch (err) {
      setMessages(prev => [...prev, {
        sender: 'assistant',
        text: "🚨 Failed to reach operations server. Ensure port bindings are active.",
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      }]);
    } finally {
      setIsSending(false);
    }
  };

  // Helper to parse simple markdown bold and lists for display
  const renderMessageText = (text: string) => {
    return text.split('\n').map((line, idx) => {
      // Headers
      if (line.startsWith('### ')) {
        return <h4 key={idx} className="font-bold text-sm text-slate-100 mt-3 mb-1 uppercase tracking-wider">{line.replace('### ', '')}</h4>;
      }
      if (line.startsWith('## ')) {
        return <h3 key={idx} className="font-bold text-base text-white mt-4 mb-2">{line.replace('## ', '')}</h3>;
      }
      // Bullet items
      if (line.startsWith('- ')) {
        return <li key={idx} className="ml-4 list-disc text-xs text-slate-300 mb-1">{line.replace('- ', '')}</li>;
      }
      // Bold text formatting
      let formattedLine: React.ReactNode = line;
      if (line.includes('**')) {
        const parts = line.split('**');
        formattedLine = parts.map((part, i) => i % 2 === 1 ? <strong key={i} className="text-emerald-400 font-bold">{part}</strong> : part);
      }

      return <p key={idx} className="text-xs text-slate-300 leading-relaxed mb-2 font-sans">{formattedLine}</p>;
    });
  };

  return (
    <div className="p-8 space-y-8 max-w-7xl mx-auto h-[calc(100vh-65px)] flex flex-col overflow-hidden" id="ai-assistant-wrapper">
      {/* Header Banner */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 shrink-0">
        <div>
          <h2 className="text-xl font-bold text-white tracking-tight uppercase flex items-center gap-2">
            <Bot className="w-6 h-6 text-emerald-400 animate-pulse" /> AI Operations Assistant Desk
          </h2>
          <p className="text-sm text-slate-400 mt-1">Ground truth compliance diagnostics and automated workforce scheduling advice</p>
        </div>

        <button
          onClick={handleScanClick}
          disabled={isScanning}
          className="bg-emerald-500 hover:bg-emerald-600 disabled:bg-slate-800 disabled:text-slate-500 text-slate-950 font-bold px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 cursor-pointer transition-colors"
        >
          <RefreshCw className={`w-4 h-4 ${isScanning ? 'animate-spin' : ''}`} />
          {isScanning ? 'Analyzing Database...' : 'Run Diagnostics Audit'}
        </button>
      </div>

      {/* Main Grid split */}
      <div className="flex-1 grid grid-cols-1 lg:grid-cols-2 gap-8 overflow-hidden min-h-0">
        
        {/* Left Column: AI Recommendations & Anomaly Stream */}
        <div className="bg-[#0e1626] border border-slate-800 rounded-xl p-5 shadow-xl flex flex-col overflow-hidden">
          <div className="border-b border-slate-800 pb-3 mb-4 flex justify-between items-center shrink-0">
            <h3 className="font-bold text-slate-200 text-xs uppercase tracking-wider flex items-center gap-2">
              <ShieldAlert className="w-4 h-4 text-rose-400" /> Compliance Anomalies Detected
            </h3>
            <span className="bg-rose-500/10 text-rose-400 text-[10px] font-bold font-mono px-2 py-0.5 rounded border border-rose-500/20">
              {recommendations.filter(r => r.status === 'new').length} Alerts
            </span>
          </div>

          {/* Scrolling Recommendation stream */}
          <div className="flex-1 overflow-y-auto space-y-4 pr-1">
            {paginatedItems.map((rec) => {
              const isHigh = rec.category.toLowerCase().includes('expired') || rec.category.toLowerCase().includes('missing');
              const isHighlight = rec.id === highlightRecId;
              return (
                <div
                  key={rec.id}
                  className={`p-4 bg-[#111a2e] border rounded-xl space-y-3 transition-all ${
                    isHighlight ? 'border-emerald-500 ring-2 ring-emerald-500/15' : 'border-slate-850'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className={`text-[9px] font-bold px-2 py-0.5 rounded uppercase tracking-wider ${
                      isHigh ? 'bg-rose-500/15 text-rose-400' : 'bg-amber-500/15 text-amber-400'
                    }`}>
                      {rec.category.replace('_', ' ')}
                    </span>
                    <span className="text-[10px] text-slate-500 font-mono">ID: {rec.id.substring(4, 9)}</span>
                  </div>

                  <div>
                    <p className="text-xs font-semibold text-slate-100 font-sans">{rec.reason}</p>
                    <p className="text-[11px] text-slate-400 leading-relaxed italic bg-black/25 p-2.5 rounded border-l-2 border-emerald-500 mt-2">
                      Recommendation: {rec.suggested_action}
                    </p>
                  </div>

                  <div className="flex justify-end gap-2 pt-1 border-t border-slate-850/30">
                    <button
                      onClick={() => onUpdateRecStatus(rec.id, 'ignored')}
                      className="text-slate-400 hover:text-slate-200 text-[11px] font-bold px-2.5 py-1.5 rounded cursor-pointer transition-colors"
                    >
                      Dismiss
                    </button>
                    <button
                      onClick={() => onUpdateRecStatus(rec.id, 'applied')}
                      className="bg-emerald-500 hover:bg-emerald-600 text-slate-950 text-[11px] font-bold px-3 py-1.5 rounded-lg cursor-pointer transition-all flex items-center gap-1.5"
                    >
                      Resolve <ArrowRight className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              );
            })}

            {newRecommendations.length === 0 && (
              <div className="flex flex-col items-center justify-center py-16 text-slate-500 text-center">
                <CheckCircle className="w-12 h-12 text-emerald-500/80 mb-3 animate-pulse" />
                <h4 className="font-bold text-slate-300 text-sm uppercase">Workforce Fully Compliant</h4>
                <p className="text-xs text-slate-500 max-w-xs mt-1">All passports, driving licenses, safety credentials, and handovers are fully aligned and updated!</p>
              </div>
            )}
          </div>

          <Pagination
            page={page}
            totalPages={totalPages}
            totalItems={totalItems}
            pageSize={pageSize}
            onPageChange={setPage}
          />
        </div>

        {/* Right Column: Grounded Gemini LLM Assistant Chat */}
        <div className="bg-[#0e1626] border border-slate-800 rounded-xl p-5 shadow-xl flex flex-col overflow-hidden">
          <div className="border-b border-slate-800 pb-3 mb-4 shrink-0">
            <h3 className="font-bold text-slate-200 text-xs uppercase tracking-wider flex items-center gap-2">
              <Bot className="w-4 h-4 text-emerald-400" /> Interactive Grounded Chat
            </h3>
          </div>

          {/* Message Bubbles list scrolling */}
          <div className="flex-1 overflow-y-auto space-y-4 pr-1 mb-4">
            {messages.map((msg, idx) => {
              const isAssistant = msg.sender === 'assistant';
              return (
                <div key={idx} className={`flex gap-3.5 ${isAssistant ? 'justify-start' : 'justify-end'}`}>
                  {isAssistant && (
                    <div className="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 shrink-0">
                      <Bot className="w-4.5 h-4.5 text-emerald-400" />
                    </div>
                  )}

                  <div className={`p-4 rounded-xl max-w-[85%] space-y-1 ${
                    isAssistant 
                      ? 'bg-slate-900/40 border border-slate-850 text-slate-200' 
                      : 'bg-emerald-500 text-slate-950 font-medium'
                  }`}>
                    {isAssistant ? (
                      renderMessageText(msg.text)
                    ) : (
                      <p className="text-xs leading-relaxed font-sans font-semibold">{msg.text}</p>
                    )}
                    <span className={`block text-[8px] font-mono mt-1 ${isAssistant ? 'text-slate-500' : 'text-slate-800 text-right'}`}>
                      {msg.time}
                    </span>
                  </div>
                </div>
              );
            })}
            <div ref={chatEndRef} />
          </div>

          {/* Message input prompt */}
          <form onSubmit={handleSendMessage} className="flex gap-2 bg-[#111a2e] p-2 rounded-xl border border-slate-800 shrink-0">
            <input
              type="text"
              placeholder="Ask AI assistant about workforce compliance or scheduling..."
              value={inputMessage}
              onChange={(e) => setInputMessage(e.target.value)}
              disabled={isSending}
              className="flex-1 bg-transparent border-none text-xs text-white placeholder-slate-500 focus:outline-none pl-2.5 disabled:text-slate-500"
            />
            <button
              type="submit"
              disabled={isSending || !inputMessage.trim()}
              className="bg-emerald-500 hover:bg-emerald-600 disabled:bg-slate-800 disabled:text-slate-500 text-slate-950 font-bold p-2.5 rounded-lg cursor-pointer transition-colors"
            >
              <Send className="w-4 h-4" />
            </button>
          </form>
        </div>

      </div>
    </div>
  );
}
