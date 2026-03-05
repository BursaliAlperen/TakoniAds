-- ============================================================
--  SNG BATTLEGROUND | AizenServer (ModuleScript)
--  ServerScriptService > AizenServer
--  Görev: Oyuncu verisi, yetenek hasarı, yükseltme sistemi
-- ============================================================

local Players           = game:GetService("Players")
local ReplicatedStorage = game:GetService("ReplicatedStorage")
local DataStoreService  = game:GetService("DataStoreService")

local Remotes          = ReplicatedStorage:WaitForChild("Remotes")
local useSkillRemote   = Remotes:WaitForChild("UseSkill")
local vfxRemote        = Remotes:WaitForChild("PlayVFX")
local upgradeRemote    = Remotes:WaitForChild("UpgradeCharacter")
local charSelectRemote = Remotes:WaitForChild("SelectCharacter")
local charSelectedEvt  = Remotes:WaitForChild("CharacterSelected")
local versionUpgEvt    = Remotes:WaitForChild("VersionUpgraded")

-- DataStore (veri kalıcılığı)
local DS
local DS_OK = pcall(function() DS = DataStoreService:GetDataStore("SNG_BG_v3") end)

-- ─── Oyuncu Verisi ────────────────────────────────────────────
local playerData = {}

local function defaultData()
	return {
		coins     = 500,  -- Başlangıç coini
		kills     = 0,
		deaths    = 0,
		character = nil,
		version   = "V1",
		owned     = {},
		inventory = {
			HogyokuPiece  = 0,
			HogyokuFusion = 0,
		},
	}
end

local function initData(player)
	local uid  = player.UserId
	local data = defaultData()

	if DS_OK and DS then
		local ok, saved = pcall(function() return DS:GetAsync("p_" .. uid) end)
		if ok and type(saved) == "table" then
			-- Eski veriyi yeni default ile birleştir (eksik alanlar ekle)
			for k, v in pairs(defaultData()) do
				if saved[k] == nil then saved[k] = v end
			end
			-- İnventory alt anahtarları kontrol et
			for k, v in pairs(defaultData().inventory) do
				if saved.inventory == nil then saved.inventory = {} end
				if saved.inventory[k] == nil then saved.inventory[k] = v end
			end
			data = saved
		end
	end

	playerData[uid] = data
	return data
end

local function getData(player)
	return playerData[player.UserId]
end

local function saveData(player)
	if not DS_OK or not DS then return end
	local data = getData(player)
	if not data then return end
	local uid  = player.UserId
	pcall(function() DS:SetAsync("p_" .. uid, data) end)
end

-- ─── Yetenek Tablosu ─────────────────────────────────────────
local AIZEN_SKILLS = {
	V1 = {
		[1] = { name="Zanpakuto Slash",  cooldown=5,  damage=28,  range=10, aoe=false },
		[2] = { name="Byakurai",         cooldown=8,  damage=42,  range=44, aoe=false, isBeam=true },
		[3] = { name="Kyoka Suigetsu",   cooldown=16, damage=32,  range=9,  aoe=true,  aoeR=8  },
	},
	V2 = {
		[1] = { name="Empowered Slash",   cooldown=5,  damage=55,  range=12, aoe=false },
		[2] = { name="Raikoho",           cooldown=9,  damage=82,  range=52, aoe=false, isBeam=true },
		[3] = { name="Kyoka Suigetsu II", cooldown=15, damage=60,  range=11, aoe=true,  aoeR=13 },
		[4] = { name="Kurohitsugi",       cooldown=22, damage=115, range=57, aoe=false, isBeam=true },
		[5] = { name="Fragor",            cooldown=13, damage=72,  range=14, aoe=true,  aoeR=15 },
	},
	V3 = {
		[1] = { name="Hogyoku Slash",       cooldown=4,  damage=88,  range=14, aoe=false },
		[2] = { name="Hogyoku Beam",        cooldown=9,  damage=145, range=64, aoe=false, isBeam=true },
		[3] = { name="Absolute Hypnosis",   cooldown=15, damage=100, range=13, aoe=true,  aoeR=20 },
		[4] = { name="Kurohitsugi Release", cooldown=23, damage=190, range=68, aoe=false, isBeam=true },
		[5] = { name="Reconstruction",      cooldown=12, damage=110, range=15, aoe=true,  aoeR=22 },
		[6] = { name="BANKAI",              cooldown=32, damage=280, range=20, aoe=true,  aoeR=24 },
	},
}

-- ─── Cooldown Sistemi ─────────────────────────────────────────
local cooldowns = {}

local function onCooldown(uid, ver, idx)
	local key  = ver .. "_" .. idx
	if not cooldowns[uid] then return false end
	local last = cooldowns[uid][key]
	if not last then return false end
	local skill = AIZEN_SKILLS[ver] and AIZEN_SKILLS[ver][idx]
	if not skill then return true end
	return (os.clock() - last) < skill.cooldown
end

local function setCooldown(uid, ver, idx)
	if not cooldowns[uid] then cooldowns[uid] = {} end
	cooldowns[uid][ver .. "_" .. idx] = os.clock()
end

-- ─── Knockback ────────────────────────────────────────────────
local function knockback(targetRoot, sourcePos, force)
	local bv       = Instance.new("BodyVelocity")
	bv.MaxForce    = Vector3.new(1e5, 1e5, 1e5)
	bv.Velocity    = (targetRoot.Position - sourcePos).Unit * force + Vector3.new(0, 20, 0)
	bv.Parent      = targetRoot
	game:GetService("Debris"):AddItem(bv, 0.22)
end

-- ─── Hasar Uygulama ───────────────────────────────────────────
local function dealDamage(attacker, char, skillData)
	local root   = char:FindFirstChild("HumanoidRootPart")
	if not root then return end
	local origin = root.Position
	local forward = root.CFrame.LookVector
	local data   = getData(attacker)
	local hitCount = 0

	for _, target in ipairs(Players:GetPlayers()) do
		if target == attacker then continue end
		local tChar = target.Character
		if not tChar then continue end
		local hum   = tChar:FindFirstChild("Humanoid")
		local tRoot = tChar:FindFirstChild("HumanoidRootPart")
		if not hum or not tRoot or hum.Health <= 0 then continue end

		local dist = (tRoot.Position - origin).Magnitude
		local hit  = false

		if skillData.aoe then
			hit = dist <= (skillData.aoeR or skillData.range)
		elseif skillData.isBeam then
			local toTarget = (tRoot.Position - origin).Unit
			local dot = forward:Dot(toTarget)
			hit = dist <= skillData.range and dot >= 0.40
		else
			local toTarget = (tRoot.Position - origin).Unit
			local dot = forward:Dot(toTarget)
			hit = dist <= skillData.range and dot >= 0.50
		end

		if hit then
			local prevHp = hum.Health
			hum:TakeDamage(skillData.damage)
			knockback(tRoot, origin, 40)
			hitCount += 1

			if prevHp > 0 and hum.Health <= 0 and data then
				data.kills += 1
				data.coins += 120
				local ls = attacker:FindFirstChild("leaderstats")
				if ls then
					local kv = ls:FindFirstChild("Kills")
					local cv = ls:FindFirstChild("Coins")
					if kv then kv.Value = data.kills end
					if cv then cv.Value = data.coins end
				end
				print(string.format("[SNG BG] %s öldürdü: %s | Coins: %d | Kills: %d",
					attacker.Name, target.Name, data.coins, data.kills))
			end
		end
	end

	-- Coin bonusu: vuruş başına
	if hitCount > 0 and data then
		local bonus = hitCount * 5
		data.coins += bonus
		local ls = attacker:FindFirstChild("leaderstats")
		if ls then
			local cv = ls:FindFirstChild("Coins")
			if cv then cv.Value = data.coins end
		end
	end
end

-- ─── UseSkill Remote ─────────────────────────────────────────
useSkillRemote.OnServerEvent:Connect(function(player, charId, version, skillIdx)
	if charId ~= "AIZEN" then return end
	local data = getData(player)
	if not data or data.character ~= "AIZEN" then return end
	if data.version ~= version then return end

	local char = player.Character
	if not char then return end
	local hum = char:FindFirstChild("Humanoid")
	if not hum or hum.Health <= 0 then return end

	local vSkills = AIZEN_SKILLS[version]
	if not vSkills or not vSkills[skillIdx] then return end

	-- Sunucu taraflı cooldown kontrolü (anti-cheat)
	if onCooldown(player.UserId, version, skillIdx) then return end
	setCooldown(player.UserId, version, skillIdx)

	dealDamage(player, char, vSkills[skillIdx])

	-- Tüm istemcilere VFX gönder
	vfxRemote:FireAllClients("AIZEN", version, skillIdx, char)
end)

-- ─── SelectCharacter Remote ──────────────────────────────────
charSelectRemote.OnServerEvent:Connect(function(player, charId, version)
	local data = getData(player)
	if not data then return end
	version = version or "V1"

	if charId == "AIZEN" then
		if not data.owned["AIZEN_V1"] then
			if data.coins < 2500 then
				Remotes.AdminMessage:FireClient(player,
					"⚠  Yetersiz Coin! AIZEN için 2500 Coin gerekli. (Mevcut: " .. data.coins .. ")",
					Color3.fromRGB(255,180,40))
				return
			end
			data.coins -= 2500
			data.owned["AIZEN_V1"] = true
			local ls = player:FindFirstChild("leaderstats")
			if ls then
				local cv = ls:FindFirstChild("Coins")
				if cv then cv.Value = data.coins end
			end
		end
	end

	data.character = charId

	-- En yüksek sahip olunan versiyona ata
	if     data.owned["AIZEN_V3"] then data.version = "V3"
	elseif data.owned["AIZEN_V2"] then data.version = "V2"
	else                               data.version = "V1"
	end

	local ls = player:FindFirstChild("leaderstats")
	if ls then
		local ch = ls:FindFirstChild("Character")
		if ch then ch.Value = charId .. " " .. data.version end
	end

	player:LoadCharacter()
	task.delay(1.0, function()
		charSelectedEvt:FireClient(player, charId, data.version)
	end)

	saveData(player)
	print(string.format("[SNG BG] %s → %s %s seçildi", player.Name, charId, data.version))
end)

-- ─── UpgradeCharacter Remote ─────────────────────────────────
upgradeRemote.OnServerEvent:Connect(function(player, charId, targetVer)
	local data = getData(player)
	if not data or data.character ~= charId then return end

	local success = false

	if charId == "AIZEN" then
		if targetVer == "V2" and data.version == "V1" then
			if data.coins >= 10000 and data.inventory.HogyokuPiece >= 1 then
				data.coins -= 10000
				data.inventory.HogyokuPiece -= 1
				data.version = "V2"
				data.owned["AIZEN_V2"] = true
				success = true
			else
				Remotes.AdminMessage:FireClient(player,
					"⚠  V2 için 10.000 Coin + 1x Hogyoku Parçası gerekli!",
					Color3.fromRGB(255,180,40))
			end
		elseif targetVer == "V3" and data.version == "V2" then
			if data.coins >= 25000 and data.inventory.HogyokuFusion >= 1 then
				data.coins -= 25000
				data.inventory.HogyokuFusion -= 1
				data.version = "V3"
				data.owned["AIZEN_V3"] = true
				success = true
			else
				Remotes.AdminMessage:FireClient(player,
					"⚠  V3 için 25.000 Coin + 1x Hogyoku Füzyonu gerekli!",
					Color3.fromRGB(255,180,40))
			end
		end
	end

	if success then
		local ls = player:FindFirstChild("leaderstats")
		if ls then
			local cv = ls:FindFirstChild("Coins")
			if cv then cv.Value = data.coins end
			local ch = ls:FindFirstChild("Character")
			if ch then ch.Value = charId .. " " .. targetVer end
		end
		versionUpgEvt:FireClient(player, charId, targetVer)
		player:LoadCharacter()
		task.delay(1.0, function()
			charSelectedEvt:FireClient(player, charId, targetVer)
		end)
		saveData(player)
		print(string.format("[SNG BG] ✓ %s → %s %s yükseltildi", player.Name, charId, targetVer))
	end
end)

-- ─── GetPlayerData RemoteFunction ────────────────────────────
Remotes:WaitForChild("GetPlayerData").OnServerInvoke = function(player)
	return getData(player)
end

-- ─── Oyuncu Eklendi / Ayrıldı ────────────────────────────────
Players.PlayerAdded:Connect(function(player)
	initData(player)

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

	-- Kayıtlı coini leaderstats'a yansıt
	task.defer(function()
		local data = getData(player)
		local ls   = player:FindFirstChild("leaderstats")
		if data and ls then
			local cv = ls:FindFirstChild("Coins")
			if cv then cv.Value = data.coins end
		end
	end)

	player.CharacterAdded:Connect(function(character)
		local hum = character:WaitForChild("Humanoid")
		hum.Died:Connect(function()
			local data = getData(player)
			if data then
				data.deaths += 1
				local ls = player:FindFirstChild("leaderstats")
				if ls then
					local dv = ls:FindFirstChild("Deaths")
					if dv then dv.Value = data.deaths end
				end
			end
		end)
	end)
end)

Players.PlayerRemoving:Connect(function(player)
	saveData(player)
	playerData[player.UserId] = nil
	cooldowns[player.UserId]  = nil
end)

-- Oyun kapanırken kaydet
game:BindToClose(function()
	for _, player in ipairs(Players:GetPlayers()) do
		saveData(player)
	end
end)

-- ─── Modül API ───────────────────────────────────────────────
local module = {}

function module.GiveCoins(player, amount)
	local data = getData(player)
	if not data then return end
	amount = math.max(0, amount)
	data.coins += amount
	local ls = player:FindFirstChild("leaderstats")
	if ls then
		local cv = ls:FindFirstChild("Coins")
		if cv then cv.Value = data.coins end
	end
	print(string.format("[SNG BG] %s +%d coin | Toplam: %d", player.Name, amount, data.coins))
end

function module.GiveItem(player, itemName, amount)
	local data = getData(player)
	if not data then return end
	amount = amount or 1
	if not data.inventory then data.inventory = {} end
	data.inventory[itemName] = (data.inventory[itemName] or 0) + amount
	print(string.format("[SNG BG] %s +%dx %s verildi", player.Name, amount, itemName))
end

function module.GetData(player)
	return getData(player)
end

function module.SetVersion(player, charId, version)
	local data = getData(player)
	if not data then return end
	data.character = charId
	data.version   = version
end

print("[SNG BATTLEGROUND] AizenServer yüklendi | V1:3 V2:5 V3:6 | DataStore: " .. (DS_OK and "✓" or "✗") .. " ✓")
return module
