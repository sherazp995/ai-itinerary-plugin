import { useAppStore } from '../stores/appStore';
import { api } from '../api/client';

export function useItinerary() {
  const {
    isGenerating, setIsGenerating, setItinerary, setAffiliateLinks,
    setView, user, setUser, setShowAuth,
  } = useAppStore();

  const generate = async (type = 'free') => {
    if (type === 'premium' && !user?.logged_in) {
      setShowAuth(true);
      return;
    }

    setIsGenerating(true);
    try {
      const data = await api.post('/itinerary/generate', { type });

      if (data.needs_payment) {
        window.location.href = data.checkout_url;
        return;
      }

      setItinerary(data.itinerary, data.itinerary_id);
      setAffiliateLinks(data.affiliate_links || []);
      setView('itinerary');

      // Update free count
      if (data.free_used !== undefined) {
        const current = user || {};
        setUser({ ...current, free_used: data.free_used, free_remaining: data.free_remaining });
      }
    } catch (err) {
      alert(err.message || 'Failed to generate itinerary');
    } finally {
      setIsGenerating(false);
    }
  };

  const downloadPdf = async (itineraryId) => {
    const res = await fetch(
      `${window.aipConfig?.api_url || '/wp-json/aip/v1'}/pdf/generate`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.aipConfig?.nonce || '',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ itinerary_id: itineraryId }),
      }
    );
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `itinerary-${itineraryId}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  };

  return { generate, downloadPdf, isGenerating };
}
