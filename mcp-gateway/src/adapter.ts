import type { Pool, RowDataPacket } from 'mysql2/promise';

/** oidc-provider persistence; this adapter writes only the OAuth store. */
export function adapter(pool: Pool) {
  return class MysqlAdapter {
    constructor(private model: string) {}
    async upsert(id: string, payload: Record<string, unknown>, expiresIn: number) {
      await pool.execute('INSERT INTO ha_oauth_store (model,id,payload,expires_at,grant_id,user_code,uid) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at),grant_id=VALUES(grant_id),user_code=VALUES(user_code),uid=VALUES(uid)', [this.model,id,JSON.stringify(payload),expiresIn ? Math.floor(Date.now()/1000)+expiresIn : null,payload.grantId ? String(payload.grantId) : null,payload.userCode ? String(payload.userCode) : null,payload.uid ? String(payload.uid) : null]);
    }
    private async lookup(column: 'id'|'uid'|'user_code', value: string) {
      const [rows] = await pool.execute<RowDataPacket[]>(`SELECT payload,consumed_at,expires_at FROM ha_oauth_store WHERE model=? AND ${column}=?`,[this.model,value]);
      const row=rows[0]; if (!row || (row.expires_at && row.expires_at<Math.floor(Date.now()/1000))) return undefined;
      const payload=JSON.parse(row.payload); if (row.consumed_at) payload.consumed=row.consumed_at; return payload;
    }
    find(id: string) { return this.lookup('id',id); }
    findByUid(uid: string) { return this.lookup('uid',uid); }
    findByUserCode(code: string) { return this.lookup('user_code',code); }
    async consume(id: string) { await pool.execute('UPDATE ha_oauth_store SET consumed_at=? WHERE model=? AND id=? AND consumed_at IS NULL',[Math.floor(Date.now()/1000),this.model,id]); }
    async destroy(id: string) { await pool.execute('DELETE FROM ha_oauth_store WHERE model=? AND id=?',[this.model,id]); }
    async revokeByGrantId(id: string) { await pool.execute('DELETE FROM ha_oauth_store WHERE grant_id=? OR (model=? AND id=?)',[id,'Grant',id]); }
  };
}
