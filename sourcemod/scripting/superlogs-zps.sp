/**
 * HLstatsX Community Edition - SourceMod plugin to generate advanced weapon logging
 * http://www.hlxcommunity.com
 * Copyright (C) 2009-2010 Nicholas Hastings (psychonic)
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

#include <sdkhooks>

#define NAME "SuperLogs: ZPS"
#define VERSION "1.1.3"

#define MAX_LOG_WEAPONS 11
#define MAX_WEAPON_LEN 12
#define PREFIX_LEN 7


int g_weapon_stats[MAXPLAYERS+1][MAX_LOG_WEAPONS][15];
char g_weapon_list[MAX_LOG_WEAPONS][MAX_WEAPON_LEN] = { 
									"870",
									"revolver",
									"ak47",
									"usp",
									"glock18c",
									"glock",
									"mp5",
									"m4",
									"supershorty",
									"winchester",
									"ppk"
								};
								
Handle g_cvar_headshots = INVALID_HANDLE;
Handle g_cvar_locations = INVALID_HANDLE;
Handle g_cvar_ktraj = INVALID_HANDLE;

bool g_logheadshots = true;
bool g_loglocations = true;
bool g_logktraj = true;

int g_iNextHitgroup[MAXPLAYERS+1];

#include <loghelper>
#include <wstatshelper>


public Plugin myinfo = {
	name = NAME,
	author = "psychonic",
	description = "Advanced logging for ZPS. Generates auxilary logging for use with log parsers such as HLstatsX and Psychostats",
	version = VERSION,
	url = "http://www.hlxcommunity.com"
};

public APLRes AskPluginLoad2(Handle myself, bool late, char[] error, int err_max)
{
	char szGameDesc[64];
	GetGameDescription(szGameDesc, sizeof(szGameDesc), true);
	if (StrContains(szGameDesc, "ZPS", false) == -1)
	{
		char szGameDir[64];
		GetGameFolderName(szGameDir, sizeof(szGameDir));
		if (StrContains(szGameDir, "zps", false) == -1)
		{
			strcopy(error, err_max, "This plugin is only supported on Zombie Panic: Source");
			return APLRes_Failure;
		}
	}	
	return APLRes_Success;
}


public void OnPluginStart()
{
	CreatePopulateWeaponTrie();
	
	g_cvar_headshots = CreateConVar("superlogs_headshots", "1", "Enable logging of headshot player action (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_locations = CreateConVar("superlogs_locations", "1", "Enable logging of location on player death (default on)", 0, true, 0.0, true, 1.0);
	g_cvar_ktraj = CreateConVar("superlogs_ktraj", "0", "Enable Psychostats \"KTRAJ\" logging (default off)", 0, true, 0.0, true, 1.0);
	HookConVarChange(g_cvar_headshots, OnCvarHeadshotsChange);
	HookConVarChange(g_cvar_locations, OnCvarLocationsChange);
	HookConVarChange(g_cvar_ktraj, OnCvarKtrajChange);
	CreateConVar("superlogs_zps_version", VERSION, NAME, FCVAR_SPONLY|FCVAR_REPLICATED|FCVAR_NOTIFY);
		
	HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
	HookEvent("player_death", Event_PlayerDeath);
	HookEvent("player_spawn", Event_PlayerSpawn);
	HookEvent("round_end", Event_RoundEnd, EventHookMode_PostNoCopy);
	HookEvent("player_disconnect", Event_PlayerDisconnect, EventHookMode_Pre);
		
	CreateTimer(1.0, LogMap);

	GetTeams();

	// apply the cvar values: they keep theirs if the plugin was reloaded,
	// and superlogs_ktraj defaults to 0 while g_logktraj starts true
	OnCvarHeadshotsChange(g_cvar_headshots, "", "");
	OnCvarLocationsChange(g_cvar_locations, "", "");
	OnCvarKtrajChange(g_cvar_ktraj, "", "");
}

public void OnAllPluginsLoaded()
{
	if (GetExtensionFileStatus("sdkhooks.ext") != 1)
	{
		SetFailState("SDK Hooks v1.3 or higher is required for SuperLogs: ZPS");
	}
	for (int i = 1; i <= MaxClients; i++)
	{
		if (IsClientInGame(i))
		{
			SDKHook(i, SDKHook_FireBulletsPost, OnFireBullets);
			SDKHook(i, SDKHook_TraceAttackPost, OnTraceAttack);
			SDKHook(i, SDKHook_OnTakeDamagePost, OnTakeDamage);
		}
	}
}

public void OnMapStart()
{
	GetTeams();
}

public void OnClientPutInServer(int client)
{
	SDKHook(client, SDKHook_FireBulletsPost, OnFireBullets);
	SDKHook(client, SDKHook_TraceAttackPost, OnTraceAttack);
	SDKHook(client, SDKHook_OnTakeDamagePost, OnTakeDamage);
	reset_player_stats(client);
}

public void OnFireBullets(int attacker, int shots, char[] weaponname)
{
	if (attacker > 0 && attacker <= MaxClients)
	{
		int weapon_index = get_weapon_index(weaponname[PREFIX_LEN]);
		if (weapon_index > -1)
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_SHOTS]++;
		}
	}
}

public void OnTraceAttack(int victim, int attacker, int inflictor, float damage, int damagetype, int ammotype, int hitbox, int hitgroup)
{
	if (hitgroup > 0 && attacker > 0 && attacker <= MaxClients && victim > 0 && victim <= MaxClients)
	{
		g_iNextHitgroup[victim] = hitgroup;
	}
}

public void OnTakeDamage(int victim, int attacker, int inflictor, float damage, int damagetype)
{	
	if (attacker > 0 && attacker <= MaxClients && victim > 0 && victim <= MaxClients)
	{
		int hitgroup = g_iNextHitgroup[victim];
		bool headshot = (GetClientHealth(victim) <= 0 && hitgroup == HITGROUP_HEAD);
		
		char weapon[MAX_WEAPON_LEN + PREFIX_LEN];
		GetClientWeapon(attacker, weapon, sizeof(weapon));
		int weapon_index = get_weapon_index(weapon[PREFIX_LEN]);
		if (weapon_index > -1)
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_HITS]++;
			g_weapon_stats[attacker][weapon_index][LOG_HIT_DAMAGE] += RoundToNearest(damage);
			// body parts are hitgroups 0-7; 10 is gear, which has no column
			if (hitgroup < 8)
			{
				g_weapon_stats[attacker][weapon_index][hitgroup + LOG_HIT_OFFSET]++;
			}
			if (headshot)
			{
				g_weapon_stats[attacker][weapon_index][LOG_HIT_HEADSHOTS]++;
			}
		}
		g_iNextHitgroup[victim] = 0;
	}
}


public Action Event_PlayerDeathPre(Handle event, const char[] name, bool dontBroadcast)
{
	// "userid"        "short"         // user ID who died                             
	// "attacker"      "short"         // user ID who killed
	// "weapon"        "string"        // weapon name killer used 
	
	int attacker = GetClientOfUserId(GetEventInt(event, "attacker"));
	int victim = GetClientOfUserId(GetEventInt(event, "userid"));
	
	if (g_loglocations)
	{
		LogKillLoc(attacker, victim);
	}
	if (g_logheadshots && g_iNextHitgroup[victim] == HITGROUP_HEAD)
	{
		LogPlayerEvent(attacker, "triggered", "headshot");
	}
	
	return Plugin_Continue;
}

public void Event_PlayerDeath(Handle event, const char[] name, bool dontBroadcast)
{
	// this extents the original player_death by a new fields
	// "userid"        "short"         // user ID who died                             
	// "attacker"      "short"         // user ID who killed
	// "weapon"        "string"        // weapon name killer used 
	
	int victim   = GetClientOfUserId(GetEventInt(event, "userid"));
	int attacker = GetClientOfUserId(GetEventInt(event, "attacker"));
	char weapon[MAX_WEAPON_LEN];
	GetEventString(event, "weapon", weapon, sizeof(weapon));
	
	if (victim > 0 && attacker > 0)
	{
		int weapon_index = get_weapon_index(weapon);
		if (weapon_index > -1)
		{
			g_weapon_stats[attacker][weapon_index][LOG_HIT_KILLS]++;
			g_weapon_stats[victim][weapon_index][LOG_HIT_DEATHS]++;
			if (GetClientTeam(attacker) == GetClientTeam(victim))
			{
				g_weapon_stats[attacker][weapon_index][LOG_HIT_TEAMKILLS]++;
			}
		}
		dump_player_stats(victim);
	}
	if (g_logktraj)
	{
		LogPSKillTraj(attacker, victim, weapon);
	}
}

public void Event_PlayerSpawn(Handle event, const char[] name, bool dontBroadcast)
{
	// "userid"        "short"         // user ID on server          

	int client = GetClientOfUserId(GetEventInt(event, "userid"));
	if (client > 0)
	{
		reset_player_stats(client);
	}
}

public void Event_RoundEnd(Handle event, const char[] name, bool dontBroadcast)
{
	WstatsDumpAll();
}

public Action Event_PlayerDisconnect(Handle event, const char[] name, bool dontBroadcast)
{
	int client = GetClientOfUserId(GetEventInt(event, "userid"));
	OnPlayerDisconnect(client);
	return Plugin_Continue;
}


public Action LogMap(Handle timer)
{
	// Called 1 second after OnPluginStart since srcds does not log the first map loaded. Idea from Stormtrooper's "mapfix.sp" for psychostats
	LogMapLoad();
	return Plugin_Stop;
}

public void OnCvarHeadshotsChange(Handle cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_logheadshots;
	g_logheadshots = GetConVarBool(g_cvar_headshots);
	
	if (old_value != g_logheadshots)
	{
		if (g_logheadshots && !g_loglocations)
		{
			HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
		else if (!g_loglocations)
		{
			UnhookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
	}
}

public void OnCvarLocationsChange(Handle cvar, const char[] oldVal, const char[] newVal)
{
	bool old_value = g_loglocations;
	g_loglocations = GetConVarBool(g_cvar_locations);
	
	if (old_value != g_loglocations)
	{
		if (g_loglocations && !g_logheadshots)
		{
			HookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
		else if (!g_logheadshots)
		{
			UnhookEvent("player_death", Event_PlayerDeathPre, EventHookMode_Pre);
		}
	}
}

public void OnCvarKtrajChange(Handle cvar, const char[] oldVal, const char[] newVal)
{
	g_logktraj = GetConVarBool(g_cvar_ktraj);
}