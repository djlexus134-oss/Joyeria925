const os = require('os');

/**
 * IPv4 activa en la LAN (DHCP). Ignora loopback, APIPA y adaptadores virtuales.
 */
function pickLanIPv4() {
  const candidates = [];
  for (const [name, addrs] of Object.entries(os.networkInterfaces())) {
    for (const addr of addrs || []) {
      const family = addr.family;
      if (family !== 'IPv4' && family !== 4) continue;
      if (addr.internal) continue;
      const ip = addr.address;
      if (!ip || ip.startsWith('169.254.')) continue;
      candidates.push({ name, ip, score: scoreInterface(name, ip) });
    }
  }
  candidates.sort((a, b) => b.score - a.score);
  return candidates[0]?.ip || '127.0.0.1';
}

function scoreInterface(name, ip) {
  let score = 0;
  if (/^(Wi-?Fi|WLAN|Ethernet|eth\d*)$/i.test(name)) score += 100;
  if (ip.startsWith('192.168.')) score += 50;
  else if (ip.startsWith('10.')) score += 40;
  else if (ip.startsWith('172.')) score += 30;
  if (/virtual|vethernet|vmware|hyper-v|docker|wsl|loopback|bluetooth/i.test(name)) score -= 80;
  return score;
}

function normalizePath(path) {
  const p = String(path || '/Joyeria/admin').trim();
  if (!p) return '/Joyeria/admin';
  return p.startsWith('/') ? p : '/' + p;
}

function buildAutoUrl(config, ip) {
  const scheme = String(config.serverScheme || 'http').replace(/:$/, '');
  const port = parseInt(config.serverPort, 10) || 8080;
  const path = normalizePath(config.serverPath);
  return scheme + '://' + ip + ':' + port + path;
}

/**
 * Resuelve serverUrl usando DHCP/local:
 * - "auto" -> http://{ip-detectada}:{serverPort}{serverPath}
 * - "http://{localIp}:8080/..." -> sustituye {localIp}
 * - serverUrlUseLocalhost: true -> fuerza 127.0.0.1 (misma PC que Apache)
 */
function resolveServerUrl(config) {
  const cfg = config || {};
  let raw = String(cfg.serverUrl || '').trim();

  if (/^auto$/i.test(raw)) {
    raw = buildAutoUrl(cfg, pickLanIPv4());
  } else if (/\{localIp\}/i.test(raw)) {
    raw = raw.replace(/\{localIp\}/gi, pickLanIPv4());
  }

  if (cfg.serverUrlUseLocalhost) {
    try {
      const u = new URL(raw);
      u.hostname = '127.0.0.1';
      raw = u.toString();
    } catch (e) {
      // dejar raw si no es URL valida
    }
  }

  return String(raw).replace(/\/$/, '');
}

module.exports = {
  resolveServerUrl,
  pickLanIPv4,
};
