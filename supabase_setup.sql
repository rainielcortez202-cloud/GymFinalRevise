-- 1. Enable Realtime (Dashboard UI Method)
-- If you are on the Free Plan and 'alter publication' fails, follow these steps:
-- a. Go to your Supabase Dashboard.
-- b. Go to 'Table Editor' -> select 'attendance' table.
-- c. Click 'Edit Table' (gear icon) -> Toggle 'Enable Realtime' to ON.
-- d. Save.

-- Alternatively, try this SQL (requires superuser or bypassrls):
-- DO $$
-- BEGIN
--     IF NOT EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
--         CREATE PUBLICATION supabase_realtime;
--     END IF;
-- END $$;
-- ALTER PUBLICATION supabase_realtime ADD TABLE attendance;

-- 2. Enable Row Level Security (RLS)
alter table users enable row level security;
alter table attendance enable row level security;
alter table sales enable row level security;

-- 3. RLS Policies for users table
-- Allow users to read their own data
create policy "Users can view their own profile" 
on users for select 
using (auth.uid()::text = id::text);

-- Allow admins to view all users
create policy "Admins can view all users" 
on users for select 
using (
  exists (
    select 1 from users 
    where id::text = auth.uid()::text and role = 'admin'
  )
);

-- 4. RLS Policies for attendance table
-- Allow users to view their own attendance
create policy "Users can view their own attendance" 
on attendance for select 
using (auth.uid()::text = user_id::text);

-- Allow staff/admin to view all attendance
create policy "Staff and Admin can view all attendance" 
on attendance for select 
using (
  exists (
    select 1 from users 
    where id::text = auth.uid()::text and role in ('admin', 'staff')
  )
);

-- 5. Full-Text Search for users
-- Add a generated column for search
alter table users add column if not exists fts tsvector 
generated always as (to_tsvector('english', full_name || ' ' || email)) stored;

-- Create an index for the fts column
create index if not exists users_fts_idx on users using gin(fts);

-- Function to search users
create or replace function search_users(query text)
returns setof users as $$
begin
  return query
  select *
  from users
  where fts @@ plainto_tsquery('english', query)
  order by ts_rank(fts, plainto_tsquery('english', query)) desc;
end;
$$ language plpgsql;

-- 6. Lockout tracking
create table if not exists ip_login_attempts (
    ip_address varchar(45) primary key,
    login_attempts int default 0,
    lockout_until timestamp null
);
