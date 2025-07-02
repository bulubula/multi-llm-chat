backend/dispatcher.php
Content Type: application/json
## post 内容示例
```
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
```
## 返回格式
```
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
```
### 流式响应的说明
json配置文件里，一级键值"stream": true,是告诉前端dispatch发送流式数据，"params"参数或这直接url里才是真正发给api的流式响应标志，因为不同的api可能流式响应标志不同，所有有两个stream标准，一个发给dispatch，一个发给api
