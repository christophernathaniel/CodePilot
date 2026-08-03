import { Link } from '@inertiajs/react';
import {
    BrainCircuit,
    Braces,
    FileSearch,
    Files,
    PanelRight,
    Search,
    Settings,
    Tags,
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

export type WorkspacePanel = 'explorer' | 'search' | 'tags';

type Props = {
    activePanel: WorkspacePanel;
    inspectorOpen: boolean;
    secondBrainActive: boolean;
    user: User;
    onPanelChange: (panel: WorkspacePanel) => void;
    onMegaSearchOpen: () => void;
    onInspectorToggle: () => void;
    onSecondBrainOpen: () => void;
};

const panelItems = [
    { panel: 'explorer' as const, label: 'Library', icon: Files },
    { panel: 'search' as const, label: 'Search', icon: Search },
    { panel: 'tags' as const, label: 'Tags', icon: Tags },
];

export function WorkspaceActivityBar({
    activePanel,
    inspectorOpen,
    secondBrainActive,
    user,
    onPanelChange,
    onMegaSearchOpen,
    onInspectorToggle,
    onSecondBrainOpen,
}: Props) {
    const getInitials = useInitials();

    return (
        <aside className="relative z-50 flex w-12 shrink-0 flex-col items-center bg-code-canvas py-2">
            <div className="mb-3 flex size-8 items-center justify-center rounded-lg bg-code-accent text-code-canvas shadow-[0_10px_28px_rgba(88,158,204,0.24)]">
                <Braces className="size-4" strokeWidth={2.4} />
                <span className="sr-only">CodePilot</span>
            </div>

            <nav className="flex w-full flex-col items-center gap-1">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            aria-label="Quick file search"
                            aria-keyshortcuts="Meta+P Control+P"
                            onClick={onMegaSearchOpen}
                            className="group relative flex h-11 w-full items-center justify-center text-code-faint transition-colors hover:text-code-text"
                        >
                            <FileSearch
                                className="size-[19px]"
                                strokeWidth={1.7}
                            />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="right">
                        Quick file search · ⌘P
                    </TooltipContent>
                </Tooltip>

                <div className="mx-auto my-1 h-px w-5 bg-code-border/70" />

                {panelItems.map((item) => {
                    const Icon = item.icon;
                    const isActive = activePanel === item.panel;

                    return (
                        <Tooltip key={item.panel}>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    aria-label={item.label}
                                    aria-pressed={isActive}
                                    onClick={() => onPanelChange(item.panel)}
                                    className="group relative flex h-11 w-full items-center justify-center text-code-faint transition-colors hover:text-code-text"
                                >
                                    {isActive && (
                                        <span className="absolute inset-y-1 left-0 w-0.5 rounded-r-full bg-code-accent" />
                                    )}
                                    <Icon
                                        className={
                                            isActive
                                                ? 'size-[19px] text-code-text'
                                                : 'size-[19px]'
                                        }
                                        strokeWidth={1.7}
                                    />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent side="right">
                                {item.label}
                            </TooltipContent>
                        </Tooltip>
                    );
                })}

                <div className="mx-auto my-1 h-px w-5 bg-code-border/70" />

                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            aria-label="Second brain"
                            aria-pressed={secondBrainActive}
                            onClick={onSecondBrainOpen}
                            className="group relative flex h-11 w-full items-center justify-center text-code-faint transition-colors hover:text-code-text"
                        >
                            {secondBrainActive && (
                                <span className="absolute inset-y-1 left-0 w-0.5 rounded-r-full bg-code-accent" />
                            )}
                            <BrainCircuit
                                className={
                                    secondBrainActive
                                        ? 'size-[19px] text-code-text'
                                        : 'size-[19px]'
                                }
                                strokeWidth={1.7}
                            />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="right">Second brain</TooltipContent>
                </Tooltip>
            </nav>

            <div className="mt-auto flex w-full flex-col items-center gap-1">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            aria-label="Toggle snippet details"
                            aria-pressed={inspectorOpen}
                            onClick={onInspectorToggle}
                            className="flex h-10 w-full items-center justify-center text-code-faint transition-colors hover:text-code-text"
                        >
                            <PanelRight
                                className={
                                    inspectorOpen
                                        ? 'size-[18px] text-code-text'
                                        : 'size-[18px]'
                                }
                                strokeWidth={1.7}
                            />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="right">Details panel</TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger asChild>
                        <Link
                            href={edit()}
                            aria-label="Account settings"
                            className="flex h-10 w-full items-center justify-center text-code-faint transition-colors hover:text-code-text"
                        >
                            <Settings
                                className="size-[18px]"
                                strokeWidth={1.7}
                            />
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent side="right">Settings</TooltipContent>
                </Tooltip>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            aria-label="Open account menu"
                            className="mb-1 rounded-full ring-code-accent/70 transition outline-none focus-visible:ring-2"
                        >
                            <Avatar className="size-7">
                                <AvatarImage
                                    src={user.avatar}
                                    alt={user.name}
                                />
                                <AvatarFallback className="bg-code-raised text-[10px] font-semibold text-code-text">
                                    {getInitials(user.name)}
                                </AvatarFallback>
                            </Avatar>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        side="right"
                        align="end"
                        className="w-56"
                    >
                        <UserMenuContent user={user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </aside>
    );
}
