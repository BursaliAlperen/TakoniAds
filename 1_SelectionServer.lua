-- ============================================================
--  SNG BATTLEGROUND | SelectionServer
--  ServerScriptService > SelectionServer  (Script)
--  Görev: Remote kurulumu, karakter stat uygulaması, admin komutları
-- ============================================================

local Players           = game:GetService("Players")
local ReplicatedStorage = game:GetService("ReplicatedStorage")

-- ─── Remote Klasörü ──────────────────────────────────────────
local Remotes = ReplicatedStorage:FindFirstChild("Remotes")
if not Remotes then
	Remotes        = Instance.new("Folder")
	Remotes.Name   = "Remotes"
	Remotes.Parent = ReplicatedStorage
end

local function re(name)
	if Remotes:FindFirstChild(name) then return Remotes[name] end
	local r = Instance.new("RemoteEvent")
	r.Name   = name
	r.Parent = Remotes
	return r
end
local function rf(name)
	if Remotes:FindFirstChild(name) then return Remotes[name] end
	local r = Instance.new("RemoteFunction")
	r.Name   = name
	r.Parent = Remotes
	return r
end

re("SelectCharacter")
re("UseSkill")
re("UpgradeCharacter")
re("PlayVFX")
re("CharacterSelected")
re("VersionUpgraded")
re("AdminMessage")
re("ShowNotification")
re("SyncStats")
rf("GetPlayerData")

-- ─── Karakter Stat Tablosu ────────────────────────────────────
local STATS = {
	AIZEN = {
		base = { hp = 250, speed = 18, jump = 60 },
		V1   = { hpMult = 1.0,  spdMult = 1.0  },
		V2   = { hpMult = 1.6,  spdMult = 1.14 },
		V3   = { hpMult = 2.5,  spdMult = 1.32 },
	},
}

local SEL_COLORS = {
	V1 = Color3.fromRGB(80, 120, 255),
	V2 = Color3.fromRGB(170, 55, 255),
	V3 = Color3.fromRGB(255, 38, 38),
}

-- ─── Stat Uygulama ────────────────────────────────────────────
local function applyStats(player, charId, version)
	local char = player.Character
	if not char then return end
	local hum = char:FindFirstChild("Humanoid")
	if not hum then return end
	local cfg   = STATS[charId]
	if not cfg  then return end
	local base  = cfg.base
	local bonus = cfg[version] or cfg.V1

	hum.MaxHealth = math.floor(base.hp * bonus.hpMult)
	hum.Health    = hum.MaxHealth
	hum.WalkSpeed = base.speed * bonus.spdMult
	hum.JumpPower = base.jump

	-- SelectionBox aura (istemci görsel)
	task.delay(0.3, function()
		if not char.Parent then return end
		for _, v in ipairs(char:GetChildren()) do
			if v:IsA("SelectionBox") then v:Destroy() end
		end
		local sb = Instance.new("SelectionBox")
		sb.Adornee             = char
		sb.Color3              = SEL_COLORS[version] or SEL_COLORS.V1
		sb.LineThickness       = 0.05
		sb.SurfaceTransparency = 0.90
		sb.SurfaceColor3       = SEL_COLORS[version] or SEL_COLORS.V1
		sb.Parent              = char
	end)

	-- İstemciye stat senkronizasyonu gönder
	Remotes.SyncStats:FireClient(player, {
		maxHp    = hum.MaxHealth,
		hp       = hum.Health,
		speed    = hum.WalkSpeed,
		charId   = charId,
		version  = version,
	})
end

-- SelectCharacter remote dinleyicisi
Remotes.SelectCharacter.OnServerEvent:Connect(function(player, charId, version)
	task.wait(0.2) -- karakter yüklenmesini bekle
	local char = player.Character
	if not char then
		player.CharacterAdded:Wait()
		task.wait(0.6)
	end
	applyStats(player, charId, version or "V1")
end)

-- Karakter yeniden doğduğunda tekrar stat uygula
Players.PlayerAdded:Connect(function(player)
	-- Leaderstats
	if not player:FindFirstChild("leaderstats") then
		local ls = Instance.new("Folder")
		ls.Name   = "leaderstats"
		ls.Parent = player
		for _, pair in ipairs({
			{"Coins",     "IntValue",    0},
			{"Kills",     "IntValue",    0},
			{"Deaths",    "IntValue",    0},
			{"Character", "StringValue", "None"},
		}) do
			local v       = Instance.new(pair[2])
			v.Name        = pair[1]
			v.Value       = pair[3]
			v.Parent      = ls
		end
	end

	player.CharacterAdded:Connect(function()
		task.wait(0.8)
		local ls  = player:FindFirstChild("leaderstats")
		local ch  = ls and ls:FindFirstChild("Character")
		if ch and ch.Value ~= "None" then
			local parts   = ch.Value:split(" ")
			local charId  = parts[1]
			local version = parts[2] or "V1"
			if STATS[charId] then
				applyStats(player, charId, version)
			end
		end
	end)
end)

-- ─── Admin Sistemi ────────────────────────────────────────────
local GAME_CREATOR_ID = game.CreatorId

local ADMINS = {
	[GAME_CREATOR_ID] = true,
	-- Buraya ek UserId ekleyebilirsin: [12345678] = true,
}

local function isAdmin(player)
	return ADMINS[player.UserId] == true
end

local function sendAdminMsg(player, text, color)
	Remotes.AdminMessage:FireClient(player, text, color)
end

local function notify(player, title, body, color)
	Remotes.ShowNotification:FireClient(player, title, body, color)
end

local VERSION_ALIASES = {
	v1       = "V1",
	v2       = "V2",
	v3       = "V3",
	hogyoku  = "V3",
	["1"]    = "V1",
	["2"]    = "V2",
	["3"]    = "V3",
}

-- ─── Admin Komut İşleyicisi ───────────────────────────────────
Players.PlayerAdded:Connect(function(player)
	player.Chatted:Connect(function(msg)
		local lower = msg:lower():gsub("%s+", "")

		-- /GiveCharAizenV1 / V2 / V3
		local suffix = lower:match("^/givecharaizen(.+)$")
		if suffix then
			if not isAdmin(player) then
				sendAdminMsg(player,
					"⛔  Bu komutu kullanma yetkin yok! Sadece SNG BATTLEGROUND yapımcısı kullanabilir.",
					Color3.fromRGB(255, 60, 60))
				return
			end
			local ver = VERSION_ALIASES[suffix]
			if not ver then
				sendAdminMsg(player,
					"⚠  Geçersiz versiyon! Kullanım: /GiveCharAizenV1 | V2 | V3 | Hogyoku",
					Color3.fromRGB(255, 180, 40))
				return
			end
			local ok, AizenServer = pcall(function()
				return require(game.ServerScriptService:FindFirstChild("AizenServer"))
			end)
			if not ok or not AizenServer then
				sendAdminMsg(player, "⚠  AizenServer modülü bulunamadı!", Color3.fromRGB(255, 180, 40))
				return
			end
			local data = AizenServer.GetData(player)
			if not data then
				sendAdminMsg(player, "⚠  Oyuncu verisi bulunamadı.", Color3.fromRGB(255, 180, 40))
				return
			end
			data.character         = "AIZEN"
			data.version           = ver
			data.owned["AIZEN_V1"] = true
			if ver == "V2" or ver == "V3" then data.owned["AIZEN_V2"] = true end
			if ver == "V3" then data.owned["AIZEN_V3"] = true end

			local ls = player:FindFirstChild("leaderstats")
			if ls then
				local ch = ls:FindFirstChild("Character")
				if ch then ch.Value = "AIZEN " .. ver end
			end
			player:LoadCharacter()
			task.delay(1.0, function()
				Remotes.CharacterSelected:FireClient(player, "AIZEN", ver)
				applyStats(player, "AIZEN", ver)
			end)
			sendAdminMsg(player,
				"✅  [SNG BATTLEGROUND] Admin komutu uygulandı → AIZEN " .. ver .. " verildi!",
				Color3.fromRGB(80, 255, 130))
			print(string.format("[SNG BATTLEGROUND ADMIN] %s → AIZEN %s verildi (komut: '%s')",
				player.Name, ver, msg))
			return
		end

		-- /GiveCoins <miktar>
		local coinAmt = lower:match("^/givecoins(%d+)$")
		if coinAmt and isAdmin(player) then
			local ok, AizenServer = pcall(function()
				return require(game.ServerScriptService:FindFirstChild("AizenServer"))
			end)
			if ok and AizenServer then
				AizenServer.GiveCoins(player, tonumber(coinAmt))
				sendAdminMsg(player, "✅  +" .. coinAmt .. " Coin verildi!", Color3.fromRGB(80,255,130))
			end
			return
		end

		-- /GiveItem <itemName>
		local itemName = lower:match("^/giveitem(.+)$")
		if itemName and isAdmin(player) then
			local ok, AizenServer = pcall(function()
				return require(game.ServerScriptService:FindFirstChild("AizenServer"))
			end)
			if ok and AizenServer then
				local items = {
					hogyokupiece  = "HogyokuPiece",
					hogyokufusion = "HogyokuFusion",
				}
				local realItem = items[itemName]
				if realItem then
					AizenServer.GiveItem(player, realItem, 1)
					sendAdminMsg(player, "✅  1x " .. realItem .. " verildi!", Color3.fromRGB(80,255,130))
				else
					sendAdminMsg(player, "⚠  Geçersiz item! (HogyokuPiece / HogyokuFusion)", Color3.fromRGB(255,180,40))
				end
			end
			return
		end

		-- /Kill <hedef>
		local killTarget = msg:lower():match("^/kill%s+(.+)$")
		if killTarget and isAdmin(player) then
			for _, p in ipairs(Players:GetPlayers()) do
				if p.Name:lower() == killTarget then
					local c = p.Character
					local h = c and c:FindFirstChild("Humanoid")
					if h then h.Health = 0 end
					sendAdminMsg(player, "✅  " .. p.Name .. " öldürüldü.", Color3.fromRGB(80,255,130))
					return
				end
			end
			sendAdminMsg(player, "⚠  Oyuncu bulunamadı: " .. killTarget, Color3.fromRGB(255,180,40))
			return
		end

		-- /Respawn
		if lower == "/respawn" and isAdmin(player) then
			player:LoadCharacter()
			return
		end
	end)
end)

print("[SNG BATTLEGROUND] SelectionServer + Admin Komutları yüklendi ✓")
