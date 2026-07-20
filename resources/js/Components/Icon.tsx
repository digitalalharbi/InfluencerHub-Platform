import {
  LayoutDashboard, Building2, Bookmark, Handshake, Users, Video, UserPlus,
  Inbox, Megaphone, GitMerge, Image, FileText, ShieldCheck, ClipboardCheck,
  Wallet, BarChart3, Plug, Settings, Search, LogOut, Rocket, Plus, Menu,
  ChevronLeft, Circle, Gauge, Activity, Radar, CalendarDays, ListChecks,
  TrendingUp, Receipt, type LucideProps,
} from 'lucide-react';

const MAP = {
  'layout-dashboard': LayoutDashboard,
  'building-2': Building2,
  bookmark: Bookmark,
  handshake: Handshake,
  users: Users,
  video: Video,
  'user-plus': UserPlus,
  inbox: Inbox,
  megaphone: Megaphone,
  'git-merge': GitMerge,
  image: Image,
  'file-text': FileText,
  'shield-check': ShieldCheck,
  'clipboard-check': ClipboardCheck,
  wallet: Wallet,
  'bar-chart-3': BarChart3,
  plug: Plug,
  settings: Settings,
  search: Search,
  'log-out': LogOut,
  rocket: Rocket,
  plus: Plus,
  menu: Menu,
  'chevron-left': ChevronLeft,
  circle: Circle,
  gauge: Gauge,
  activity: Activity,
  radar: Radar,
  'calendar-days': CalendarDays,
  'list-checks': ListChecks,
  'trending-up': TrendingUp,
  receipt: Receipt,
} as const;

export type IconName = keyof typeof MAP;

export function Icon({ name, size = 18, ...rest }: { name: IconName; size?: number } & LucideProps) {
  const Cmp = MAP[name] ?? Circle;
  return <Cmp size={size} strokeWidth={2} {...rest} />;
}
