import { useAppStore } from '../stores/appStore';
import { api } from '../api/client';

export function useChat() {
  const {
    messages, addMessage, setMessages, isSending, setIsSending,
    setCollectedData, setReady, setMissing, config,
  } = useAppStore();

  const sendMessage = async (text) => {
    if (!text.trim() || isSending) return;

    addMessage({ role: 'user', content: text });
    setIsSending(true);

    try {
      const data = await api.post('/chat/message', { message: text });

      addMessage({ role: 'assistant', content: data.bot_message });
      setCollectedData(data.collected_data || {});
      setReady(data.ready || false);
      setMissing(data.missing || []);
    } catch (err) {
      addMessage({ role: 'assistant', content: 'Sorry, something went wrong. Please try again.' });
    } finally {
      setIsSending(false);
    }
  };

  const resetChat = async () => {
    try {
      const data = await api.post('/chat/reset');
      setMessages([{ role: 'assistant', content: data.bot_message }]);
      setCollectedData({});
      setReady(false);
      setMissing([]);
    } catch (err) {
      // ignore
    }
  };

  return { sendMessage, resetChat, messages, isSending };
}
