backend/dispatcher.php
Content Type: application/json
//post 内容示例
  //选择provider，根据这个读取json，根据json选择导入的php
  //对话的格式，每个api的php都根据这个格式把对话转化成自己所需的对话格式
{
  "provider": "deepseek",
  "conversation": [
    { "role": "user", "text": "123123，这是用来测试对话记忆的，简单回复即可" },
    { "role": "assistant", "text": "好的" },
    { "role": "user", "text": "我上一句话提到的数字串是多少" },
    { "role": "assistant", "text": "123123" },
    { "role": "user", "text": "上下文记忆有长度限制吗" }
  ]
}
//返回格式
{
	"id":"8bce80ce-6b78-4924-a10b-ca7105c82258",
	"object":"chat.completion",
	"created":1751190752,
	"model":"deepseek-chat",
	"choices":[{
		"index":0,
		"message":{
			"role":"assistant",
			"content":"回复"
		},
		"logprobs":null,
		"finish_reason":"stop"
	}],
	"usage":{
		"prompt_tokens":6,
		"completion_tokens":315,
		"total_tokens":321,
		"prompt_tokens_details":{
			"cached_tokens":0
		},
		"prompt_cache_hit_tokens":0,
		"prompt_cache_miss_tokens":6
	},
	"system_fingerprint":"fp_8802369eaa_prod0623_fp8_kvcache"
}




{
    "id": "conv-1750821553707-83",
    "title": "测试",
    "messages": [
        {
            "author": "User",
            "content": "快速简短回复",
            "className": "user-message",
            "isStream": false
        },
        {
            "author": "User",
            "content": "测试",
            "className": "user-message",
            "isStream": false
        },
        {
            "author": "qwen",
            "content": "你好！有什么我可以帮你的吗？",
            "className": "bot-message",
            "isStream": false
        }
    ]
}