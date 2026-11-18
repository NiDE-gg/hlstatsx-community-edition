/**
 * HLstatsX Community Edition - SourceMod plugin to generate advanced weapon logging
 * http://www.hlxcommunity.com
 * Copyright (C) 2009 Nicholas Hastings (psychonic)
 * Copyright (C) 2007-2008 TTS Oetzel & Goerz GmbH
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

#pragma semicolon 1
#pragma newdecls required
 
#include <sourcemod>
#include <sdktools>

#define NAME "SuperLogs: GES"
#define VERSION "1.1.4"

#define MAX_LOG_WEAPONS 18
#define MAX_WEAPON_LEN 13
#define PREFIX_LEN 7

#define GES


int g_weapon_stats[MAXPLAYERS+1][MAX_LOG_WEAPONS][20];
char g_weapon_list[MAX_LOG_WEAPONS][MAX_WEAPON_LEN] = {
									"pp7",
									"pp7_silenced",
									"dd44",
									"klobb",
									"cmag",
									"kf7",
									"zmg",
									"d5k",
									"d5k_silenced",
									"phantom",
									"AR33",
									"rcp90",
									"shotgun",
									"auto_shotgun",
									"sniper_rifle",
									"golden_gun",
									"silver_pp7",
									"golden_pp7"
								};

/*new const String:g_weapon_loglist[MAX_LOG_WEAPONS][] = {
									"#GE_PP7",
									"#GE_PP7_SILENCED",
									"#GE_DD44",
									"#GE_Klobb",
									"#GE_CougarMagnum",
									"#GE_KF7Soviet",
									"#GE_ZMG",
									"#GE_D5K",
									"#GE_D5K_SILENCED",
									"#GE_Phantom",
									"#GE_AR33",
									"#GE_RCP90",
									"#GE_Shotgun",
									"#GE_AutoShotgun",
									"#GE_SniperRifle",
									"#GE_GoldenGun",
									"#GE_SilverPP7",
									"#GE_GoldPP7"
								};
*/								
ConVar g_cvar_wstats = null;
ConVar g_cvar_headshots = null;
ConVar g_cvar_locations = null;
ConVar g_cvar_actions = null;
ConVar g_cvar_classchanges = null;

bool g_logwstats = true;
bool g_logheadshots = true;
bool g_loglocations = true;
bool g_logactions = true;
bool g_logclasschanges = true;

#include <loghelper>
#include <wstatshelper>


public Plugin myinfo = {
	name = NAME,
	author = "psychonic",
	description = "Advanced logging for GoldenEye: Source. Generates auxilary logging for use with log parsers such as HLstatsX and Psychostats",
	version = VERSION,
	url = "http://www.hlxcommunity.com"
};


public void OnPluginStart()
{
	CreatePopulateWeaponTrie();
	
	g_cvar_wstats = CreateConVar("superlogs_wstats", "1", "Enable logging of weapon stats (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_headshots = CreateConVar("superlogs_headshots", "1", "Enable logging of headshot player action (default off)", 0, true, 0.0, true, 1.0);
	g_cvar_locations = CreateConVar("superlogs_locations", "1", "Enable logging of location on player death (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_actions = CreateConVar("superlogs_actions", "1", "Enable logging of actions, such as round winner and awards won (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_classchanges = CreateConVar("superlogs_classchanges", "1", "Enable logging of character changes (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_wstats.AddChangeHook(OnCvarWstatsChange);
	g_cvar_headshots.AddChangeHook(OnCvarHeadshotsChange);
	g_cvar_locations.AddChangeHook(OnCvarLocationsChange);
	g_cvar_actions.AddChangeHook(OnCvarActionsChange);
	g_cvar_classchanges.AddChangeHook(OnCvarClasschangesChange);
	CreateConVar("superlogs_ges_version", VERSION, NAME, FCVAR_SPONLY|FCVAR_REPLICATED|FCVAR_NOTIFY);
		
	hook_wstats();
	HookEvent("player_hurt",  Event_PlayerHurt);
	HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
	HookEvent("player_changeident",	Event_RoleChange);
	HookEvent("round_end",    Event_RoundEnd);
		
	CreateTimer(1.0, LogMap);
	
	GetTeams();

	// apply the cvar values they kept if the plugin was reloaded
	OnCvarWstatsChange(g_cvar_wstats, "", "");
	OnCvarHeadshotsChange(g_cvar_headshots, "", "");
	OnCvarLocationsChange(g_cvar_locations, "", "");
	OnCvarActionsChange(g_cvar_actions, "", "");
	OnCvarClasschangesChange(g_cvar_classchanges, "", "");
}


public void OnMapStart()
{
	GetTeams();
}

void hook_wstats()
{
	HookEvent("player_death", Event_PlayerDeath);
	HookEvent("player_shoot",  Event_PlayerShoot);
	HookEvent("player_spawn", Event_PlayerSpawn);
	HookEvent("player_disconnect", Event_PlayerDisconnect, EventHookMode_Pre);
}

void unhook_wstats()
{
	UnhookEvent("player_death", Event_PlayerDeath);
	UnhookEvent("player_shoot",  Event_PlayerShoot);
	UnhookEvent("player_spawn", Event_PlayerSpawn);
	UnhookEvent("player_disconnect", Event_PlayerDisconnect, EventHookMode_Pre);
}

public void OnClientPutInServer(int client)
{
	reset_player_stats(client);
}


public void Event_PlayerShoot(Event event, const char[] name, bool dontBroadcast)
{
	// "userid"		"local" 	// user ID on server
	// "weapon"	"local" 	// weapon name
	// "mode"		"local" 	// weapon mode [0 normal, 1 aimed]

	int attacker = GetClientOfUserId(event.GetInt("userid"));
	if (g_logwstats && attacker > 0)
	{
		char weapon[MAX_WEAPON_LEN+PREFIX_LEN];
		event.GetString("weapon", weapon, sizeof(weapon));
		int weapon_index = get_weapon_index(weapon[PREFIX_LEN]);
		if (weapon_index > -1)
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_SHOTS]++;
		}
	}
}


public void Event_PlayerHurt(Event event, const char[] name, bool dontBroadcast)
{
	// "userid"		"local" 	// user ID who was hurt
	// "attacker"	"local" 	// user ID who attacked
	// "weapon"	"local" 	// weapon name attacker used
	// "health"		"local" 	// health remaining
	// "armor"		"local" 	// armor remaining
	// "damage"	"local" 	// how much damage in this attack
	// "hitgroup"	"local" 	// what hitgroup was hit

	int attacker  = GetClientOfUserId(event.GetInt("attacker"));
	int hitgroup  = event.GetInt("hitgroup");
	bool headshot = (event.GetInt("health") <= 0 && hitgroup == HITGROUP_HEAD);
	
	if (g_logwstats && attacker > 0)
	{
		char weapon[MAX_WEAPON_LEN+PREFIX_LEN];
		event.GetString("weapon", weapon, sizeof(weapon));

		int weapon_index = get_weapon_index(weapon[PREFIX_LEN]);
		if (weapon_index > -1)
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_HITS]++;
			g_weapon_stats[attacker][weapon_index][LOG_HIT_DAMAGE]  += event.GetInt("damage");
			if (hitgroup < 8)
			{
				g_weapon_stats[attacker][weapon_index][hitgroup + LOG_HIT_OFFSET]++;
			}
			if (headshot)
			{
				g_weapon_stats[attacker][weapon_index][LOG_HIT_HEADSHOTS]++;
			}
		}
	}
	if (g_logheadshots && headshot)
	{
		LogPlayerEvent(attacker, "triggered", "headshot");
	}
}

public Action Event_PlayerDeathPre(Event event, const char[] name, bool dontBroadcast)
{
	LogKillLoc(GetClientOfUserId(event.GetInt("attacker")), GetClientOfUserId(event.GetInt("userid")));
	
	return Plugin_Continue;
}


public void Event_PlayerDeath(Event event, const char[] name, bool dontBroadcast)
{
	// "userid"		"short"	// user ID who died
	// "attacker"	"short"	// user ID who killed
	// "weapon"	"string" 	// weapon name killed used
	// "weaponid"	"short"	// GE Weapon ID (for easy comparison)
	// "custom"		"byte"	// Used for achievements

	int victim   = GetClientOfUserId(event.GetInt("userid"));
	int attacker = GetClientOfUserId(event.GetInt("attacker"));
	if (g_logwstats && (victim > 0) && (attacker > 0))
	{
		char weapon[MAX_WEAPON_LEN+PREFIX_LEN];
		event.GetString("weapon", weapon, sizeof(weapon));
		int weapon_index = get_weapon_index(weapon[PREFIX_LEN]);
		if (weapon_index > -1)
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_KILLS]++;
			g_weapon_stats[victim][weapon_index][LOG_HIT_DEATHS]++;
			if (GetClientTeam(attacker) == GetClientTeam(victim))
			{
				g_weapon_stats[attacker][weapon_index][LOG_HIT_TEAMKILLS]++;
			}
			dump_player_stats(victim);
		}
	}
}

public void Event_PlayerSpawn(Event event, const char[] name, bool dontBroadcast)
{
	// "userid"        "short"         // user ID on server          

	int client = GetClientOfUserId(event.GetInt("userid"));
	if (client > 0)
	{
		reset_player_stats(client);
	}
}

public void Event_RoundEnd(Event event, const char[] name, bool dontBroadcast)
{
	if (g_logwstats)
	{
		WstatsDumpAll();
	}
	if (g_logactions)
	{
		int winner = event.GetInt("winnerid");
		if (winner > 0)
		{
			LogPlayerEvent(winner, "triggered", "Round_Win", true);
		}
		else
		{
			winner = event.GetInt("teamid");
			if (winner > 0)
			{
				LogTeamEvent(winner, "triggered", "Round_Win_Team");
			}
		}
		int awards[6];
		awards[0] = event.GetInt("award1_id");
		awards[1] = event.GetInt("award2_id");
		awards[2] = event.GetInt("award3_id");
		awards[3] = event.GetInt("award4_id");
		awards[4] = event.GetInt("award5_id");
		awards[5] = event.GetInt("award6_id");

		int winners[6];
		winners[0] = event.GetInt("award1_winner");
		winners[1] = event.GetInt("award2_winner");
		winners[2] = event.GetInt("award3_winner");
		winners[3] = event.GetInt("award4_winner");
		winners[4] = event.GetInt("award5_winner");
		winners[5] = event.GetInt("award6_winner");

		for (int i = 0; i < 6; i++)
		{
			if (winners[i] > 0)
			{
				switch(awards[i])
				{
					case 0:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_DEADLY", true);
					case 1:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_HONORABLE", true);
					case 2:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_PROFESSIONAL", true);
					case 3:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_MARKSMANSHIP", true);
					case 4:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_AC10", true);
					case 5:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_FRANTIC", true);
					case 6:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_WTA", true);
					case 7:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_LEMMING", true);
					case 8:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_LONGIN", true);
					case 9:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_SHORTIN", true);
					case 10:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_DISHONORABLE", true);
					case 11:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_NOTAC10", true);
					case 12:
						LogPlayerEvent(winners[i], "triggered", "GE_AWARD_MOSTLYHARMLESS", true);
				}
			}
		}
	}
}


public void Event_RoleChange(Event event, const char[] name, bool dontBroadcast)
{
	// "playerid"	"short"
	// "ident"		"string"
	
	int client = event.GetInt("playerid");

	if (client > 0)
	{
		char ident[32];
		event.GetString("ident", ident, sizeof(ident));
		LogRoleChange(client, ident);
	}	
}

public Action Event_PlayerDisconnect(Event event, const char[] name, bool dontBroadcast)
{
	int client = GetClientOfUserId(event.GetInt("userid"));
	OnPlayerDisconnect(client);
	return Plugin_Continue;
}

public Action LogMap(Handle timer)
{
	// Called 1 second after OnPluginStart since srcds does not log the first map loaded. Idea from Stormtrooper's "mapfix.sp" for psychostats
	LogMapLoad();
	return Plugin_Stop;
}


public void OnCvarWstatsChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logwstats;
	g_logwstats = g_cvar_wstats.BoolValue;
	
	if (old_value != g_logwstats)
	{
		if (g_logwstats)
		{
			hook_wstats();
			if (!g_logheadshots)
			{
				HookEvent("player_hurt", Event_PlayerHurt);
			}
			if (!g_logactions)
			{
				HookEvent("round_end", Event_RoundEnd);
			}
		}
		else
		{
			unhook_wstats();
			if (!g_logheadshots)
			{
				UnhookEvent("player_hurt", Event_PlayerHurt);
			}
			if (!g_logactions)
			{
				UnhookEvent("round_end", Event_RoundEnd);
			}
		}
	}
}



public void OnCvarHeadshotsChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logheadshots;
	g_logheadshots = g_cvar_headshots.BoolValue;
	
	if (old_value != g_logheadshots)
	{
		if (g_logheadshots && !g_logwstats)
		{
			HookEvent("player_hurt", Event_PlayerHurt);
		}
		else if (!g_logwstats)
		{
			UnhookEvent("player_hurt", Event_PlayerHurt);
		}
	}
}

public void OnCvarLocationsChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_loglocations;
	g_loglocations = g_cvar_locations.BoolValue;
	
	if (old_value != g_loglocations)
	{
		if (g_loglocations)
		{
			HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
		else
		{
			UnhookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
	}
}

public void OnCvarActionsChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logactions;
	g_logactions = g_cvar_actions.BoolValue;
	
	if (old_value != g_logactions)
	{
		if (g_logactions && !g_logwstats)
		{
			HookEvent("round_end", Event_RoundEnd);
		}
		else if (!g_logwstats)
		{
			// round_end also dumps the weapon stats: keep it while those are logged
			UnhookEvent("round_end", Event_RoundEnd);
		}
	}
}

public void OnCvarClasschangesChange(ConVar cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logclasschanges;
	g_logclasschanges = g_cvar_classchanges.BoolValue;
	
	if (old_value != g_logclasschanges)
	{
		if (g_logclasschanges)
		{
			HookEvent("player_changeident",	Event_RoleChange);
		}
		else
		{
			UnhookEvent("player_changeident",	Event_RoleChange);
		}
	}
}
