const config = window.aipConfig || {};

const baseUrl = config.api_url || '/wp-json/aip/v1';
const nonce = config.nonce || '';

async function request(method, endpoint, body = null) {
  const opts = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    credentials: 'same-origin',
  };

  if (body) {
    opts.body = JSON.stringify(body);
  }

  const res = await fetch(`${baseUrl}${endpoint}`, opts);
  const data = await res.json();

  if (!res.ok) {
    throw new Error(data.message || data.code || 'Request failed');
  }

  return data;
}

export const api = {
  get: (endpoint) => request('GET', endpoint),
  post: (endpoint, body) => request('POST', endpoint, body),
};
